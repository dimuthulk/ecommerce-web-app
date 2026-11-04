/**
 * Admin back office — core helpers + the list pages (dashboard, products, variant stock,
 * categories, orders, customers) and the sign-in page.
 * More complex pages (product form, order, customer, reports, settings) are in admin-pages.js.
 * Everything talks to the JSON API through window.api (assets/js/api.js).
 */
(() => {
    const Kit = (window.AdminKit = {});
    Kit.pages = {};

    const meta = (name) => (document.querySelector('meta[name="' + name + '"]') || {}).content || '';
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const adminBase = meta('admin-base') || '/admin/';
    const currency = meta('store-currency') || 'LKR';

    Object.assign(Kit, { $, $$, adminBase, currency });

    /* ---------- Formatting ---------- */
    Kit.esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const esc = Kit.esc;

    Kit.money = (n) => {
        const number = Number(n || 0);
        return currency + ' ' + number.toLocaleString('en-US', { minimumFractionDigits: number % 1 ? 2 : 0, maximumFractionDigits: 2 });
    };
    const toDate = (text) => new Date(String(text).replace(' ', 'T'));
    Kit.date = (text) => (text ? toDate(text).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '');
    Kit.dateTime = (text) => {
        if (!text) return '';
        const d = toDate(text);
        return Kit.date(text) + ' · ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    };
    Kit.url = (path) => adminBase + path;
    const pad = (n) => String(n).padStart(2, '0');
    const ymd = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());

    /** Dates for the period drop-downs (same choices as the dashboard). */
    Kit.periodRange = (key) => {
        const today = new Date();
        const day = (offset) => ymd(new Date(today.getFullYear(), today.getMonth(), today.getDate() + offset));
        if (key === 'last_30') return { from: day(-29), to: day(0) };
        if (key === 'last_month') return { from: ymd(new Date(today.getFullYear(), today.getMonth() - 1, 1)), to: ymd(new Date(today.getFullYear(), today.getMonth(), 0)) };
        if (key === 'all') return {};
        return { from: ymd(new Date(today.getFullYear(), today.getMonth(), 1)), to: day(0) };
    };

    const STATUS_LABELS = { pending: 'Pending', confirmed: 'Confirmed', processing: 'Confirmed', shipped: 'Shipped', delivered: 'Delivered', cancelled: 'Cancelled', refunded: 'Refunded' };
    const STOCK_LABELS = { in: 'In stock', low: 'Low stock', out: 'Out of stock' };
    Kit.badge = (status) => '<span class="badge badge--' + esc(status) + '">' + esc(STATUS_LABELS[status] || status) + '</span>';
    Kit.stockBadge = (status) => '<span class="badge badge--' + esc(status) + '">' + esc(STOCK_LABELS[status] || status) + '</span>';
    Kit.statusLabel = (status) => STATUS_LABELS[status] || status;

    Kit.debounce = (fn, wait = 250) => {
        let timer;
        return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); };
    };

    /* ---------- Messages + errors ---------- */
    Kit.toast = (title, type = 'success', text = '') => {
        if (window.showToast) window.showToast({ title, type, text });
    };

    Kit.banner = (el, kind, message, retry) => {
        el.innerHTML = '<p class="alert alert--' + kind + '" role="' + (kind === 'error' ? 'alert' : 'status') + '">' + esc(message) +
            (retry ? ' <button type="button" class="btn btn--ghost" data-retry>Retry</button>' : '') + '</p>';
        if (retry) $('[data-retry]', el).addEventListener('click', retry);
    };

    Kit.clearErrors = (form) => {
        $$('.field-error', form).forEach((n) => n.remove());
        $$('[aria-invalid]', form).forEach((n) => n.removeAttribute('aria-invalid'));
    };
    Kit.fieldErrors = (form, fields) => {
        Object.entries(fields || {}).forEach(([name, message]) => {
            if (!message) return;
            const input = form.querySelector('[name="' + name + '"]');
            const note = document.createElement('p');
            note.className = 'field-error';
            note.id = 'err-' + name;
            note.textContent = message;
            if (input) {
                input.setAttribute('aria-invalid', 'true');
                input.setAttribute('aria-describedby', note.id);
                (input.closest('.field') || input.parentElement).append(note);
            } else {
                form.prepend(note);
            }
        });
    };

    /** Yes/no dialog. Resolves true when confirmed. */
    Kit.confirm = ({ title, text, confirm = 'Confirm', danger = false }) => new Promise((resolve) => {
        if (!window.HTMLDialogElement) { resolve(window.confirm(title + '\n' + text)); return; }
        const dialog = document.createElement('dialog');
        dialog.className = 'admin-dialog';
        dialog.innerHTML = '<h2 class="admin-card-title">' + esc(title) + '</h2><p>' + esc(text) + '</p>' +
            '<div class="admin-actions"><button type="button" class="btn ' + (danger ? 'btn--danger' : 'btn--primary') + '" data-yes>' + esc(confirm) + '</button>' +
            '<button type="button" class="btn btn--outline" data-no>Keep as is</button></div>';
        document.body.append(dialog);
        const close = (answer) => { dialog.close(); dialog.remove(); resolve(answer); };
        $('[data-yes]', dialog).addEventListener('click', () => close(true));
        $('[data-no]', dialog).addEventListener('click', () => close(false));
        dialog.addEventListener('cancel', () => close(false));
        dialog.showModal();
    });

    /* ---------- Table + pager rendering ---------- */
    /** columns: [{ label, cell: (row) => html }] — the label also appears on small screens */
    Kit.table = (columns, rows, { selectedKey, rowKey } = {}) =>
        '<div class="table-wrap"><table class="admin-table"><thead><tr>' +
        columns.map((c) => '<th scope="col">' + esc(c.label) + '</th>').join('') + '</tr></thead><tbody>' +
        rows.map((row) => '<tr' + (rowKey ? ' data-key="' + esc(rowKey(row)) + '"' : '') + (selectedKey && rowKey && rowKey(row) === selectedKey ? ' class="is-selected"' : '') + '>' +
            columns.map((c) => '<td data-label="' + esc(c.label) + '">' + c.cell(row) + '</td>').join('') + '</tr>').join('') +
        '</tbody></table></div>';

    Kit.pager = (el, meta, goTo, noun = 'items') => {
        if (!meta || !meta.total) { el.innerHTML = ''; return; }
        const from = (meta.page - 1) * meta.per_page + 1;
        const to = Math.min(meta.total, meta.page * meta.per_page);
        let html = '<p class="admin-pager__info">' + (meta.pages === 1 ? 'Showing all ' + meta.total + ' ' + noun : 'Showing ' + from + '–' + to + ' of ' + meta.total) + '</p>';
        if (meta.pages > 1) {
            html += meta.page > 1 ? '<button type="button" class="page-link" data-page="' + (meta.page - 1) + '">Previous</button>' : '<span class="page-link" aria-disabled="true">Previous</span>';
            for (let n = 1; n <= meta.pages; n++) {
                html += '<button type="button" class="page-link page-link--num" data-page="' + n + '"' + (n === meta.page ? ' aria-current="page"' : '') + ' aria-label="Page ' + n + '">' + n + '</button>';
            }
            html += meta.page < meta.pages ? '<button type="button" class="page-link" data-page="' + (meta.page + 1) + '">Next</button>' : '<span class="page-link" aria-disabled="true">Next</span>';
        }
        el.innerHTML = html;
        $$('[data-page]', el).forEach((b) => b.addEventListener('click', () => goTo(Number(b.dataset.page))));
    };

    /**
     * A filterable, paginated list.
     * options: endpoint, results, pager, summary?, params(): object, columns, rowKey?, noun, empty, onLoaded?(data, meta)
     */
    Kit.list = (o) => {
        let page = 1;
        let selected = null;
        const state = { reload };

        async function load() {
            try {
                const { data, meta } = await api.get(o.endpoint, Object.assign({}, o.params(), { page }));
                if (meta.page !== page) page = meta.page;
                o.results.innerHTML = data.length
                    ? Kit.table(o.columns, data, { rowKey: o.rowKey, selectedKey: selected })
                    : '<p class="admin-empty">' + esc(o.empty) + '</p>';
                Kit.pager(o.pager, meta, (n) => { page = n; load(); }, o.noun);
                if (o.summary) o.summary.textContent = o.summaryText ? o.summaryText(data, meta) : '';
                if (o.rowKey && o.onSelect) {
                    $$('tbody tr', o.results).forEach((tr) => tr.addEventListener('click', (event) => {
                        if (event.target.closest('a, button')) return;
                        selected = tr.dataset.key;
                        $$('tbody tr', o.results).forEach((r) => r.classList.toggle('is-selected', r === tr));
                        o.onSelect(data.find((row) => o.rowKey(row) === selected));
                    }));
                    tr0();
                }
                if (o.onLoaded) o.onLoaded(data, meta, state);
            } catch (error) {
                o.pager.innerHTML = '';
                Kit.banner(o.results, 'error', (o.errorTitle || 'The list could not be loaded') + '. ' + error.message, load);
            }
        }
        function tr0() { /* rows are clickable */ $$('tbody tr', o.results).forEach((tr) => { tr.style.cursor = 'pointer'; }); }
        function reload(resetPage = false) { if (resetPage) page = 1; return load(); }
        state.select = (key) => { selected = key; };
        load();
        return state;
    };

    /* ---------- Shell: drawer + sign out ---------- */
    const drawer = $('#admin-drawer');
    const openButton = $('[data-drawer-open]');
    const setDrawer = (open) => {
        if (!drawer) return;
        drawer.hidden = !open;
        document.body.style.overflow = open ? 'hidden' : '';
        if (openButton) openButton.setAttribute('aria-expanded', String(open));
        if (open) $('[data-drawer-close]', drawer).focus(); else if (openButton) openButton.focus();
    };
    if (openButton) openButton.addEventListener('click', () => setDrawer(true));
    $$('[data-drawer-close]').forEach((b) => b.addEventListener('click', () => setDrawer(false)));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drawer && !drawer.hidden) setDrawer(false); });
    window.matchMedia('(min-width: 1025px)').addEventListener('change', (e) => { if (e.matches) setDrawer(false); });

    $$('[data-signout]').forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        try { await api.post('/auth/logout'); } catch (e) { /* leave anyway */ }
        window.location.href = Kit.url('login');
    }));

    /* ==========================================================================
       Pages
       ========================================================================== */

    /* ---------- Sign in ---------- */
    Kit.pages.login = () => {
        const form = $('#admin-login');
        const box = $('#login-error');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            Kit.clearErrors(form);
            box.hidden = true;
            const button = $('button[type=submit]', form);
            button.disabled = true;
            try {
                const { data } = await api.post('/admin/login', { email: form.email.value, password: form.password.value });
                api.setCsrf(data.csrf);
                window.location.href = data.redirect;
            } catch (error) {
                box.textContent = error.message;
                box.hidden = false;
                Kit.fieldErrors(form, error.fields);
                button.disabled = false;
            }
        });
    };

    /* ---------- Dashboard ---------- */
    Kit.pages.dashboard = () => {
        const app = $('#app');
        const select = $('#period');

        async function load() {
            try {
                const { data } = await api.get('/admin/dashboard', { period: select.value });
                const s = data.orders.by_status;
                const max = Math.max(1, ...Object.values(s));
                const order = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
                const summary = order.filter((k) => s[k] > 0 && k !== 'delivered' && k !== 'cancelled').map((k) => s[k] + ' ' + Kit.statusLabel(k)).join(' · ') || 'No orders in this period';

                app.innerHTML =
                    '<div class="stat-grid">' +
                    '<section class="card stat"><p class="stat__label">Orders recorded</p><p class="stat__value">' + data.orders.total + '</p><p class="stat__foot">' + esc(summary) + '</p></section>' +
                    '<section class="card stat"><p class="stat__label">Recorded sales</p><p class="stat__value">' + esc(Kit.money(data.sales.merchandise)) + '</p><p class="stat__foot">Delivered merchandise only · ' + data.sales.delivered_orders + ' order' + (data.sales.delivered_orders === 1 ? '' : 's') + '</p></section>' +
                    '<section class="card stat"><p class="stat__label">Low-stock variants</p><p class="stat__value">' + data.stock.low + '</p><p class="stat__foot">Threshold ≤ ' + data.stock.limit + ' · excludes sold-out variants</p></section>' +
                    '</div>' +
                    '<div class="admin-two">' +
                    '<section class="card admin-panel"><h2 class="admin-card-title">Order status overview</h2><div class="status-rows">' +
                    order.map((k) => '<div class="status-row">' + Kit.badge(k) + '<span class="bar"><span style="width:' + Math.round((s[k] / max) * 100) + '%"></span></span><span>' + s[k] + ' order' + (s[k] === 1 ? '' : 's') + '</span></div>').join('') +
                    '</div></section>' +
                    '<section class="card admin-panel"><h2 class="admin-card-title">Stock needs attention</h2>' +
                    (data.stock.attention.length
                        ? '<div class="attention-list">' + data.stock.attention.map((v) => '<div class="attention-item"><div class="attention-item__row"><span>' + esc(v.product_sku + ' · ' + v.color + ' / ' + v.size) + '</span><span>' + v.available + ' left</span></div>' + Kit.stockBadge(v.status) + '</div>').join('') + '</div>'
                        : '<p class="admin-note">Every variant has enough stock.</p>') +
                    '<p><a class="btn btn--outline" style="margin-top:1rem" href="' + Kit.url('variants') + '">Manage variant stock</a></p></section>' +
                    '</div>' +
                    '<h2 class="admin-h2">Recent orders</h2>' +
                    (data.recent.length
                        ? Kit.table([
                            { label: 'Order', cell: (r) => '<strong>' + esc(r.number) + '</strong>' },
                            { label: 'Customer', cell: (r) => esc(r.customer) },
                            { label: 'Placed', cell: (r) => esc(Kit.date(r.placed_at)) },
                            { label: 'Total', cell: (r) => esc(Kit.money(r.total)) },
                            { label: 'Status', cell: (r) => Kit.badge(r.status) },
                            { label: 'Action', cell: (r) => '<a class="row-link" href="' + Kit.url('order?number=' + encodeURIComponent(r.number)) + '">View order</a>' },
                        ], data.recent)
                        : '<p class="admin-empty">No orders yet.</p>') +
                    '<p style="margin-top:1rem"><a class="btn btn--outline" href="' + Kit.url('orders') + '">View all orders</a></p>' +
                    '<p class="admin-note">Reports are operational summaries, not full accounting. Delivery charges are shown separately from sales.</p>';
            } catch (error) {
                Kit.banner(app, 'error', 'The dashboard could not be loaded. ' + error.message, load);
            }
        }
        select.addEventListener('change', load);
        load();
    };

    /* ---------- Products catalogue ---------- */
    Kit.pages.products = () => {
        const q = $('#q');
        const category = $('#category');

        api.get('/admin/categories').then(({ data }) => {
            category.insertAdjacentHTML('beforeend', data.map((c) => '<option value="' + esc(c.name) + '">' + esc(c.name) + '</option>').join(''));
        }).catch(() => {});

        const list = Kit.list({
            endpoint: '/admin/products', results: $('#results'), pager: $('#pager'), summary: $('#summary'),
            noun: 'products', empty: 'No products match your search.', errorTitle: 'Products could not be loaded',
            params: () => ({ q: q.value.trim(), category: category.value }),
            summaryText: (data, meta) => meta.summary && !q.value.trim() && !category.value
                ? meta.summary.products + ' products · ' + meta.summary.categories + ' categories · ' + meta.summary.units + ' units available'
                : meta.total + ' matching product' + (meta.total === 1 ? '' : 's'),
            columns: [
                { label: 'Product / SKU', cell: (p) => '<strong>' + esc(p.name) + ' / ' + esc(p.sku) + '</strong>' + (p.published ? '' : ' <span class="muted">(unpublished)</span>') },
                { label: 'Category', cell: (p) => esc(p.category) },
                { label: 'Price', cell: (p) => esc(Kit.money(p.price)) },
                { label: 'Stock', cell: (p) => p.units + ' unit' + (p.units === 1 ? '' : 's') },
                { label: 'Action', cell: (p) => '<a class="row-link" href="' + Kit.url('product?id=' + p.id) + '">Edit product</a>' },
            ],
        });
        q.addEventListener('input', Kit.debounce(() => list.reload(true)));
        category.addEventListener('change', () => list.reload(true));
    };

    /* ---------- Variant stock ---------- */
    Kit.pages.variants = () => {
        const q = $('#q');
        const status = $('#status');
        const editor = $('#editor');
        $('#note').textContent = 'Low-stock threshold: ≤ 3 units; zero is Out of stock. Stock is checked again on the server so two people cannot oversell at the same time.';
        let current = null;

        const key = (v) => v.product_id + '|' + v.color + '|' + v.size;

        function open(variant) {
            current = variant;
            editor.hidden = false;
            editor.innerHTML =
                '<h2 class="admin-card-title">Update selected variant</h2>' +
                '<p class="admin-card-title" style="margin-top:1rem;font-size:1rem">' + esc(variant.product + ' · ' + variant.color + ' / ' + variant.size) + '</p>' +
                '<p class="admin-note">Current stock: ' + variant.available + ' unit' + (variant.available === 1 ? '' : 's') + ' · ' + esc(variant.sku) + '</p>' +
                '<form class="admin-form" novalidate><div class="field"><label for="v-stock">New available stock <span aria-hidden="true">*</span></label>' +
                '<input class="input" id="v-stock" name="stock" type="number" min="0" step="1" inputmode="numeric" value="' + variant.available + '" required>' +
                '<p class="field-hint">Direct stock update: no negative or fractional inventory.</p></div>' +
                '<div class="admin-actions" style="margin-top:0"><button type="submit" class="btn btn--primary">Save stock</button><button type="button" class="btn btn--outline" data-discard>Discard changes</button></div></form>';
            const form = $('form', editor);
            $('[data-discard]', editor).addEventListener('click', () => { form.stock.value = variant.available; Kit.clearErrors(form); });
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                Kit.clearErrors(form);
                try {
                    const { data } = await api.patch('/admin/variants', { product_id: variant.product_id, color: variant.color, size: variant.size, stock: form.stock.value });
                    Kit.toast('Stock saved', 'success', data.sku + ' now has ' + data.available + ' available.');
                    list.select(key(data));
                    await list.reload();
                    open(data);
                } catch (error) {
                    Kit.fieldErrors(form, error.fields && Object.keys(error.fields).length ? error.fields : { stock: error.message });
                }
            });
        }

        const list = Kit.list({
            endpoint: '/admin/variants', results: $('#results'), pager: $('#pager'),
            noun: 'variants', empty: 'No variants match your search.', errorTitle: 'Variants could not be loaded',
            params: () => ({ q: q.value.trim(), status: status.value }),
            rowKey: key,
            onSelect: open,
            columns: [
                { label: 'Product variant', cell: (v) => '<strong>' + esc(v.product + ' / ' + v.color + ' / ' + v.size) + '</strong>' },
                { label: 'SKU', cell: (v) => esc(v.sku) },
                { label: 'Available', cell: (v) => String(v.available) },
                { label: 'Status', cell: (v) => Kit.stockBadge(v.status) },
            ],
            onLoaded: (data) => {
                if (!current && data.length) { list.select(key(data[0])); $$('tbody tr', $('#results'))[0].classList.add('is-selected'); open(data[0]); }
                if (!data.length) { editor.hidden = true; current = null; }
            },
        });
        const refilter = () => { current = null; list.select(null); list.reload(true); };
        q.addEventListener('input', Kit.debounce(refilter));
        status.addEventListener('change', refilter);
    };

    /* ---------- Categories ---------- */
    Kit.pages.categories = () => {
        const q = $('#q');
        const editor = $('#editor');
        let rows = [];

        function form(category) {
            const edit = !!category;
            const c = category || { name: '', slug: '', description: '', visible: true, products: 0 };
            editor.innerHTML =
                '<h2 class="admin-card-title">' + (edit ? 'Edit category' : 'New category') + '</h2>' +
                '<form class="admin-form" novalidate>' +
                '<div class="field"><label for="c-name">Category name <span aria-hidden="true">*</span></label><input class="input" id="c-name" name="name" maxlength="80" value="' + esc(c.name) + '" required></div>' +
                '<div class="field"><label for="c-slug">URL slug <span aria-hidden="true">*</span></label><input class="input" id="c-slug" name="slug" maxlength="100" value="' + esc(c.slug) + '" required><p class="field-hint">Unique, lowercase, hyphen-separated.</p></div>' +
                '<div class="field"><label for="c-desc">Description</label><textarea class="textarea" id="c-desc" name="description" rows="3" maxlength="1000">' + esc(c.description) + '</textarea></div>' +
                '<label class="check"><input type="checkbox" name="visible"' + (c.visible ? ' checked' : '') + '><span>Visible in store navigation</span></label>' +
                '<button type="submit" class="btn btn--primary btn--block">Save category</button>' +
                '<button type="button" class="btn btn--outline btn--block" data-new>Create new category</button>' +
                (edit ? '<button type="button" class="btn btn--ghost btn--text-danger btn--block" data-delete' + (c.products ? ' disabled title="Reassign its products first"' : '') + '>Delete category</button>' : '') +
                '</form>';
            const f = $('form', editor);
            $('[data-new]', editor).addEventListener('click', () => form(null));
            if (edit) $('[data-delete]', editor).addEventListener('click', async () => {
                if (!(await Kit.confirm({ title: 'Delete "' + c.name + '"?', text: 'This removes the category. Products must be reassigned first.', confirm: 'Delete category', danger: true }))) return;
                try { await api.del('/admin/categories/' + c.id); Kit.toast('Category deleted', 'success'); form(null); list.reload(); }
                catch (error) { Kit.toast('Could not delete', 'error', error.message); }
            });
            f.addEventListener('submit', async (event) => {
                event.preventDefault();
                Kit.clearErrors(f);
                const body = { name: f.name.value, slug: f.slug.value, description: f.description.value, visible: f.visible.checked };
                try {
                    if (edit) await api.patch('/admin/categories/' + c.id, body); else await api.post('/admin/categories', body);
                    Kit.toast('Category saved', 'success');
                    await list.reload();
                    const saved = rows.find((r) => r.name === body.name);
                    form(saved || null);
                } catch (error) { Kit.fieldErrors(f, error.fields); if (!error.fields || !Object.keys(error.fields).length) Kit.toast('Could not save', 'error', error.message); }
            });
        }

        const list = Kit.list({
            endpoint: '/admin/categories', results: $('#results'), pager: document.createElement('div'),
            noun: 'categories', empty: 'No categories match your search.', errorTitle: 'Categories could not be loaded',
            params: () => ({ q: q.value.trim() }),
            columns: [
                { label: 'Category', cell: (c) => '<strong>' + esc(c.name) + '</strong>' },
                { label: 'Slug', cell: (c) => esc(c.slug) },
                { label: 'Products', cell: (c) => String(c.products) },
                { label: 'Action', cell: (c) => '<button type="button" class="row-link" data-edit="' + c.id + '">Edit category</button>' },
            ],
            onLoaded: (data, meta) => {
                rows = data;
                $('#note').textContent = meta.total + ' clothing categories. Reassign products before removing a category.';
                $$('[data-edit]', $('#results')).forEach((b) => b.addEventListener('click', () => form(rows.find((r) => r.id === Number(b.dataset.edit)))));
            },
        });
        q.addEventListener('input', Kit.debounce(() => list.reload(true)));
        form(null);
        api.get('/admin/categories').then(({ data }) => { if (data.length) form(data[0]); }).catch(() => {});
    };

    /* ---------- Orders ---------- */
    Kit.pages.orders = () => {
        const q = $('#q');
        const status = $('#status');
        const period = $('#period');
        const tabs = $('#tabs');
        period.value = 'all';

        const range = () => Kit.periodRange(period.value);
        const list = Kit.list({
            endpoint: '/admin/orders', results: $('#results'), pager: $('#pager'),
            noun: 'orders', empty: 'No orders match these filters.', errorTitle: 'Orders could not be loaded',
            params: () => Object.assign({ q: q.value.trim(), status: status.value }, range()),
            columns: [
                { label: 'Order', cell: (o) => '<strong>' + esc(o.number) + '</strong>' },
                { label: 'Customer', cell: (o) => esc(o.customer) },
                { label: 'Placed', cell: (o) => esc(Kit.date(o.placed_at)) },
                { label: 'Total', cell: (o) => esc(Kit.money(o.total)) },
                { label: 'Status', cell: (o) => Kit.badge(o.status) },
                { label: 'Action', cell: (o) => '<a class="row-link" href="' + Kit.url('order?number=' + encodeURIComponent(o.number)) + '">View order</a>' },
            ],
            onLoaded: (data, meta) => {
                const c = meta.counts;
                const items = [['', 'All', c.all], ['pending', 'Pending', c.pending], ['confirmed', 'Confirmed', c.confirmed], ['shipped', 'Shipped', c.shipped], ['delivered', 'Delivered', c.delivered], ['cancelled', 'Cancelled', c.cancelled]];
                tabs.innerHTML = items.map(([value, label, n]) => '<button type="button" role="tab" aria-selected="' + (status.value === value) + '" class="btn ' + (status.value === value ? 'btn--primary' : 'btn--outline') + '" data-status="' + value + '">' + label + ' (' + n + ')</button>').join('');
                $$('[data-status]', tabs).forEach((b) => b.addEventListener('click', () => { status.value = b.dataset.status; list.reload(true); }));
            },
        });
        q.addEventListener('input', Kit.debounce(() => list.reload(true)));
        status.addEventListener('change', () => list.reload(true));
        period.addEventListener('change', () => list.reload(true));
    };

    /* ---------- Customers ---------- */
    Kit.pages.customers = () => {
        const q = $('#q');
        const list = Kit.list({
            endpoint: '/admin/customers', results: $('#results'), pager: $('#pager'), summary: $('#summary'),
            noun: 'customers', empty: 'No customers match your search.', errorTitle: 'Customers could not be loaded',
            params: () => ({ q: q.value.trim() }),
            summaryText: (data, meta) => meta.total + ' customer' + (meta.total === 1 ? '' : 's') + ' with an account',
            columns: [
                { label: 'Customer', cell: (c) => '<strong>' + esc(c.name) + '</strong>' },
                { label: 'Email', cell: (c) => esc(c.email) },
                { label: 'Orders', cell: (c) => String(c.orders) },
                { label: 'Action', cell: (c) => '<a class="row-link" href="' + Kit.url('customer?id=' + c.id) + '">View customer</a>' },
            ],
        });
        q.addEventListener('input', Kit.debounce(() => list.reload(true)));
    };

    /* ---------- Start the page ---------- */
    const start = () => {
        const name = document.body.dataset.adminPage;
        const run = Kit.pages[name];
        if (run) run();
    };
    // admin-pages.js registers the remaining pages first, then both files start together
    Kit.start = start;
    window.addEventListener('DOMContentLoaded', () => setTimeout(start, 0));
})();
