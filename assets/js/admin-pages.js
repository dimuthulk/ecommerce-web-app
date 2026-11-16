/**
 * Admin back office — the larger pages: product form, order, customer, reports, settings.
 * Uses the helpers from admin.js (window.AdminKit) and the JSON API (window.api).
 */
(() => {
  const Kit = window.AdminKit;
  const { $, $$, esc } = Kit;

  const placeholderSvg =
    '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><path d="M20 10 6 18l5 12 7-3v27h28V27l7 3 5-12-14-8c-2 4-6.5 6-12 6s-10-2-12-6z"/></svg>';
  const SIZES = ["XS", "S", "M", "L", "XL"];
  const variantSku = (sku, color, size) =>
    sku +
    "-" +
    color
      .replace(/[^A-Za-z]/g, "")
      .slice(0, 2)
      .toUpperCase() +
    "-" +
    size;

  /* ==========================================================================
       Product create / edit
       ========================================================================== */
  Kit.pages.product = async () => {
    const app = $("#app");
    const id = Number(app.dataset.id) || 0;
    const edit = id > 0;
    document.title = (edit ? "Product edit" : "Create product") + " | admin";
    $(".admin-title").textContent = edit ? "Product edit" : "Create product";
    $(".admin-crumbs").textContent =
      "Admin / " + (edit ? "Product edit" : "Create product");

    let product = null;
    let variants = [];
    let editingIndex = -1;

    try {
      const [categories, loaded] = await Promise.all([
        api.get("/admin/categories"),
        edit
          ? api.get("/admin/products/" + id)
          : Promise.resolve({ data: null }),
      ]);
      product = loaded.data;
      variants = product
        ? product.variants.map((v) => ({
            color: v.color,
            size: v.size,
            stock: v.available,
          }))
        : [];
      render(categories.data);
    } catch (error) {
      Kit.banner(
        app,
        "error",
        "The product could not be loaded. " + error.message,
        () => window.location.reload(),
      );
    }

    function render(categories) {
      const p = product || {
        name: "",
        sku: "",
        category: "",
        price: "",
        description: "",
        published: true,
        image_alt: "",
        images: [],
      };
      const known = [
        ...new Set([
          "Chalk",
          "Ink",
          "Clay",
          "Sand",
          "Stone",
          ...variants.map((v) => v.color),
        ]),
      ];
      const sizes = [...new Set([...SIZES, ...variants.map((v) => v.size)])];

      app.innerHTML =
        '<div id="save-status" aria-live="polite"></div>' +
        '<div class="admin-split">' +
        '<div class="admin-stack">' +
        '<section class="card admin-panel"><h2>Product details</h2>' +
        '<form class="admin-form" id="product-form" novalidate>' +
        '<div class="field"><label for="p-name">Product name <span aria-hidden="true">*</span></label><input class="input" id="p-name" name="name" maxlength="190" value="' +
        esc(p.name) +
        '" required></div>' +
        '<div class="field"><label for="p-sku">Product SKU <span aria-hidden="true">*</span></label><input class="input" id="p-sku" name="sku" maxlength="60" value="' +
        esc(p.sku) +
        '" required></div>' +
        '<div class="field"><label for="p-category">Category <span aria-hidden="true">*</span></label><select class="select" id="p-category" name="category" required><option value="">Choose a category</option>' +
        categories
          .map(
            (c) =>
              '<option value="' +
              esc(c.name) +
              '"' +
              (c.name === p.category ? " selected" : "") +
              ">" +
              esc(c.name) +
              "</option>",
          )
          .join("") +
        "</select></div>" +
        '<div class="field"><label for="p-price">Price · ' +
        esc(Kit.currency) +
        ' <span aria-hidden="true">*</span></label><input class="input" id="p-price" name="price" type="number" min="0" step="0.01" inputmode="decimal" value="' +
        esc(p.price) +
        '" required><p class="field-hint">Non-negative amount.</p></div>' +
        '<div class="field"><label for="p-desc">Description <span aria-hidden="true">*</span></label><textarea class="textarea" id="p-desc" name="description" rows="4" maxlength="5000" required>' +
        esc(p.description) +
        "</textarea></div>" +
        '<label class="check"><input type="checkbox" name="published"' +
        (p.published ? " checked" : "") +
        "><span>Published in storefront</span></label>" +
        "</form></section>" +
        '<section class="card admin-panel"><h2>Size / color variants</h2>' +
        '<p class="admin-note">Every size and color combination must be unique. Stock uses whole numbers, zero or greater.</p>' +
        '<div class="admin-form"><div class="admin-form-row">' +
        '<div class="field"><label for="v-size">Size <span aria-hidden="true">*</span></label><select class="select" id="v-size">' +
        sizes.map((s) => "<option>" + esc(s) + "</option>").join("") +
        "</select></div>" +
        '<div class="field"><label for="v-color">Color <span aria-hidden="true">*</span></label><input class="input" id="v-color" list="color-list" maxlength="30" autocomplete="off"><datalist id="color-list">' +
        known.map((c) => '<option value="' + esc(c) + '">').join("") +
        "</datalist></div>" +
        '<div class="field"><label for="v-stock">Variant stock <span aria-hidden="true">*</span></label><input class="input" id="v-stock" type="number" min="0" step="1" inputmode="numeric" value="0"></div>' +
        '</div><div><button type="button" class="btn btn--outline" id="add-variant">Add variant</button> <button type="button" class="btn btn--ghost" id="cancel-variant" hidden>Cancel edit</button></div>' +
        '<p class="field-error" id="variant-error" hidden></p></div>' +
        '<div id="variants-table"></div></section>' +
        "</div>" +
        '<aside class="card admin-panel" id="images-panel"><h2>Product images</h2>' +
        imagesHtml(p) +
        "</aside>" +
        "</div>" +
        '<div class="admin-actions">' +
        '<button type="submit" class="btn btn--primary" form="product-form" id="save">Save product</button>' +
        '<button type="button" class="btn btn--outline" id="discard">Cancel changes</button>' +
        '<a class="btn btn--ghost" href="' +
        Kit.url("products") +
        '">Back to catalogue</a></div>';

      drawVariants();
      bind();
      if (new URLSearchParams(location.search).get("saved")) {
        Kit.banner(
          $("#save-status"),
          "success",
          "Product saved. It is now " +
            (p.published ? "live in the storefront." : "saved as unpublished."),
        );
      }
    }

    function imagesHtml(p) {
      if (!edit)
        return '<p class="admin-note">Save the product first, then add its pictures here.</p>';
      const first = p.images[0];
      return (
        '<div class="image-main">' +
        (first
          ? '<img src="' +
            esc(first.url) +
            '" alt="' +
            esc(p.image_alt || p.name) +
            '">'
          : placeholderSvg) +
        "</div>" +
        '<p class="admin-note">' +
        (first
          ? "Main picture. The first picture is shown first in the store."
          : "No pictures yet. Customers see a grey placeholder.") +
        "</p>" +
        '<input type="file" id="image-file" class="sr-file" accept="image/jpeg,image/png,image/webp,image/gif">' +
        '<button type="button" class="btn btn--outline btn--block" id="upload" style="margin-top:.75rem">' +
        (first ? "Add additional image" : "Upload image") +
        "</button>" +
        '<p class="field-hint">JPG, PNG, WEBP or GIF, up to 3 MB, at most 6 pictures.</p>' +
        '<div class="admin-form"><div class="field"><label for="p-alt">Image alternative text</label><input class="input" id="p-alt" name="image_alt" form="product-form" maxlength="190" value="' +
        esc(p.image_alt) +
        '" placeholder="e.g. Essential Cotton Tee in Chalk, front view"></div></div>' +
        (p.images.length
          ? '<div class="image-list">' +
            p.images
              .map(
                (img, i) =>
                  '<div class="image-item"><img src="' +
                  esc(img.url) +
                  '" alt=""><span>' +
                  (i === 0 ? "Main picture" : "Picture " + (i + 1)) +
                  '</span><span class="image-item__buttons">' +
                  (i > 0
                    ? '<button type="button" data-main="' +
                      i +
                      '">Make main</button>'
                    : "") +
                  '<button type="button" data-remove="' +
                  i +
                  '">Remove</button></span></div>',
              )
              .join("") +
            "</div>"
          : "")
      );
    }

    function drawVariants() {
      const sku = $("#p-sku").value.trim() || "SKU";
      $("#variants-table").innerHTML = variants.length
        ? Kit.table(
            [
              {
                label: "Variant",
                cell: (v) =>
                  "<strong>" + esc(v.color + " / " + v.size) + "</strong>",
              },
              {
                label: "SKU",
                cell: (v) => esc(variantSku(sku, v.color, v.size)),
              },
              {
                label: "Stock",
                cell: (v) => v.stock + (v.stock === 0 ? " · Sold out" : ""),
              },
              {
                label: "Action",
                cell: (v) =>
                  '<button type="button" class="row-link" data-edit-variant="' +
                  variants.indexOf(v) +
                  '">Edit</button> / <button type="button" class="row-link" data-remove-variant="' +
                  variants.indexOf(v) +
                  '">Remove</button>',
              },
            ],
            variants,
          )
        : '<p class="admin-empty">No variants yet. Add at least one size and color.</p>';
      $$("[data-edit-variant]", app).forEach((b) =>
        b.addEventListener("click", () =>
          startEditVariant(Number(b.dataset.editVariant)),
        ),
      );
      $$("[data-remove-variant]", app).forEach((b) =>
        b.addEventListener("click", () => {
          variants.splice(Number(b.dataset.removeVariant), 1);
          cancelEditVariant();
          drawVariants();
        }),
      );
    }

    function startEditVariant(i) {
      editingIndex = i;
      $("#v-size").value = variants[i].size;
      $("#v-color").value = variants[i].color;
      $("#v-stock").value = variants[i].stock;
      $("#add-variant").textContent = "Update variant";
      $("#cancel-variant").hidden = false;
      $("#v-color").focus();
    }
    function cancelEditVariant() {
      editingIndex = -1;
      $("#add-variant").textContent = "Add variant";
      $("#cancel-variant").hidden = true;
      $("#v-color").value = "";
      $("#v-stock").value = 0;
    }

    function bind() {
      const form = $("#product-form");
      const variantError = $("#variant-error");
      $("#p-sku").addEventListener("input", drawVariants);
      $("#cancel-variant").addEventListener("click", cancelEditVariant);

      $("#add-variant").addEventListener("click", () => {
        const color = $("#v-color").value.trim();
        const size = $("#v-size").value;
        const stock = $("#v-stock").value;
        variantError.hidden = true;
        if (!color) return showVariantError("Please enter a color.");
        if (stock === "" || !/^\d+$/.test(stock))
          return showVariantError("Stock uses whole numbers, zero or greater.");
        const clash = variants.findIndex(
          (v, i) =>
            i !== editingIndex &&
            v.color.toLowerCase() === color.toLowerCase() &&
            v.size === size,
        );
        if (clash >= 0)
          return showVariantError(
            "That size and color already exists. Edit it instead.",
          );
        const row = { color, size, stock: Number(stock) };
        if (editingIndex >= 0) variants[editingIndex] = row;
        else variants.push(row);
        cancelEditVariant();
        drawVariants();
      });
      const showVariantError = (message) => {
        variantError.textContent = message;
        variantError.hidden = false;
      };

      $("#discard").addEventListener("click", () => window.location.reload());

      form.addEventListener("submit", async (event) => {
        event.preventDefault();
        Kit.clearErrors(form);
        variantError.hidden = true;
        const status = $("#save-status");
        status.innerHTML = "";
        const body = {
          name: form.name.value,
          sku: form.sku.value,
          category: form.category.value,
          price: form.price.value,
          description: form.description.value,
          published: form.published.checked,
          image_alt: form.elements.image_alt
            ? form.elements.image_alt.value
            : "",
          variants,
        };
        const button = $("#save");
        button.disabled = true;
        try {
          const { data } = edit
            ? await api.patch("/admin/products/" + id, body)
            : await api.post("/admin/products", body);
          if (!edit) {
            window.location.href = Kit.url(
              "product?id=" + data.id + "&saved=1",
            );
            return;
          }
          product = data;
          Kit.banner(
            status,
            "success",
            "Product saved. It is " +
              (data.published
                ? "live in the storefront."
                : "saved as unpublished."),
          );
          Kit.toast("Product saved", "success");
          const keep = variants;
          variants = data.variants.map((v) => ({
            color: v.color,
            size: v.size,
            stock: v.available,
          }));
          void keep;
          drawVariants();
        } catch (error) {
          Kit.banner(
            status,
            "error",
            "Product could not be saved. " +
              (error.fields && Object.keys(error.fields).length
                ? "Check the highlighted fields."
                : error.message),
          );
          const fields = Object.assign({}, error.fields);
          if (fields.variants) {
            showVariantError(fields.variants);
            delete fields.variants;
          }
          Kit.fieldErrors(form, fields);
          status.scrollIntoView({ block: "nearest" });
        } finally {
          button.disabled = false;
        }
      });

      if (edit) bindImages();
    }

    function bindImages() {
      const file = $("#image-file");
      $("#upload").addEventListener("click", () => file.click());
      file.addEventListener("change", async () => {
        if (!file.files.length) return;
        const form = new FormData();
        form.append("image", file.files[0]);
        try {
          const { data } = await api.upload(
            "/admin/products/" + id + "/images",
            form,
          );
          product = data;
          $("#images-panel").innerHTML =
            "<h2>Product images</h2>" + imagesHtml(data);
          bindImages();
          Kit.toast("Image uploaded", "success");
        } catch (error) {
          Kit.toast("Upload failed", "error", error.message);
          file.value = "";
        }
      });
      $$("[data-remove]", app).forEach((b) =>
        b.addEventListener("click", async () => {
          if (
            !(await Kit.confirm({
              title: "Remove this picture?",
              text: "The picture is deleted from the store.",
              confirm: "Remove picture",
              danger: true,
            }))
          )
            return;
          try {
            const { data } = await api.del(
              "/admin/products/" + id + "/images",
              { path: product.images[Number(b.dataset.remove)].path },
            );
            product = data;
            $("#images-panel").innerHTML =
              "<h2>Product images</h2>" + imagesHtml(data);
            bindImages();
          } catch (error) {
            Kit.toast("Could not remove", "error", error.message);
          }
        }),
      );
      $$("[data-main]", app).forEach((b) =>
        b.addEventListener("click", async () => {
          const order = product.images.map((x) => x.path);
          const [moved] = order.splice(Number(b.dataset.main), 1);
          order.unshift(moved);
          try {
            const { data } = await api.patch(
              "/admin/products/" + id + "/images",
              { order },
            );
            product = data;
            $("#images-panel").innerHTML =
              "<h2>Product images</h2>" + imagesHtml(data);
            bindImages();
          } catch (error) {
            Kit.toast("Could not reorder", "error", error.message);
          }
        }),
      );
    }
  };

  /* ==========================================================================
       Order detail
       ========================================================================== */
  Kit.pages.order = async () => {
    const app = $("#app");
    const number = app.dataset.number;
    if (!number) {
      Kit.banner(app, "error", "No order was chosen.");
      return;
    }

    async function load() {
      try {
        const { data } = await api.get(
          "/admin/orders/" + encodeURIComponent(number),
        );
        draw(data);
      } catch (error) {
        Kit.banner(
          app,
          "error",
          error.status === 404
            ? "That order was not found."
            : "The order could not be loaded. " + error.message,
          error.status === 404 ? null : load,
        );
      }
    }

    function draw(o) {
      const final = ["delivered", "cancelled", "refunded"].includes(o.status);
      const steps = ["pending", "confirmed", "shipped", "delivered"];
      const idx = Math.max(
        0,
        steps.indexOf(o.status === "processing" ? "confirmed" : o.status),
      );
      const next = steps[Math.min(idx + 1, 3)];
      const itemCount = o.items.reduce((n, i) => n + i.quantity, 0);
      const address =
        [o.address.line1, o.address.line2].filter(Boolean).join(", ") +
        ", " +
        [o.address.city, o.address.postal].filter(Boolean).join(" ");

      app.innerHTML =
        '<div class="admin-order-head"><h2 class="admin-h2" style="margin:1.5rem 0 0">' +
        esc(o.number) +
        '</h2><span style="margin-top:1.5rem">' +
        Kit.badge(o.status) +
        "</span></div>" +
        '<div class="admin-split">' +
        '<div class="admin-stack">' +
        '<section class="card admin-panel"><h2>Order items</h2>' +
        o.items
          .map(
            (i) =>
              '<div class="admin-line"><span class="image-main admin-line__image">' +
              placeholderSvg +
              "</span><div><strong>" +
              esc(i.name) +
              '</strong><p class="admin-note">' +
              esc(i.color + " / " + i.size) +
              " · Quantity " +
              i.quantity +
              "</p><p>" +
              esc(Kit.money(i.line_total)) +
              "</p></div></div>",
          )
          .join("") +
        '<div class="admin-row"><span>Placed</span><span>' +
        esc(Kit.dateTime(o.placed_at)) +
        "</span></div>" +
        '<div class="admin-row"><span>Payment</span><span>' +
        (o.payment.method === "cod"
          ? "Cash on delivery"
          : esc(o.payment.method)) +
        "</span></div></section>" +
        '<section class="card admin-panel"><h2>Customer and delivery</h2>' +
        '<p class="admin-strong">' +
        esc(o.customer.name) +
        "</p>" +
        '<p class="admin-line-text">' +
        esc(o.customer.email) +
        " · " +
        esc(o.customer.phone) +
        "</p>" +
        '<p class="admin-line-text">' +
        esc(address) +
        "</p>" +
        (o.notes
          ? '<p class="admin-note">Customer note: ' + esc(o.notes) + "</p>"
          : "") +
        (o.customer.user_id
          ? '<p style="margin-top:1rem"><a class="btn btn--outline" href="' +
            Kit.url("customer?id=" + o.customer.user_id) +
            '">View customer</a></p>'
          : '<p class="admin-note">Guest checkout (no account).</p>') +
        "</section>" +
        '<section class="card admin-panel"><h2>Status history</h2>' +
        o.history
          .map(
            (h) =>
              '<div class="history-entry"><div class="admin-row"><span>' +
              esc(Kit.dateTime(h.at)) +
              "</span><span>" +
              esc(Kit.statusLabel(h.status)) +
              "</span></div>" +
              (h.note ? '<p class="admin-note">' + esc(h.note) + "</p>" : "") +
              "</div>",
          )
          .join("") +
        '<p class="admin-note">Recorded store updates, not live courier tracking.</p></section>' +
        "</div>" +
        '<div class="admin-stack">' +
        '<section class="card admin-panel"><h2>Update order status</h2>' +
        (final
          ? '<p class="admin-note">This order is ' +
            esc(Kit.statusLabel(o.status).toLowerCase()) +
            " and can no longer be changed.</p>"
          : '<form class="admin-form" id="status-form" novalidate><div class="field"><label for="s-status">New status</label><select class="select" id="s-status" name="status">' +
            ["pending", "confirmed", "shipped", "delivered", "cancelled"]
              .map(
                (s) =>
                  '<option value="' +
                  s +
                  '"' +
                  (s === next ? " selected" : "") +
                  (s !== "cancelled" && steps.indexOf(s) <= idx
                    ? " disabled"
                    : "") +
                  ">" +
                  esc(Kit.statusLabel(s)) +
                  "</option>",
              )
              .join("") +
            "</select></div>" +
            '<div class="field"><label for="s-note">Note for the history (optional)</label><input class="input" id="s-note" name="note" maxlength="255"></div>' +
            '<button type="submit" class="btn btn--primary btn--block">Update status</button>' +
            '<button type="button" class="btn btn--danger btn--block" id="cancel-order">Cancel order</button></form>' +
            '<p class="admin-note">Delivered orders only are counted as recorded sales. Cancelling puts the items back into stock and tells the customer by e-mail.</p>') +
        "</section>" +
        '<section class="card admin-panel"><h2>Order summary</h2>' +
        '<div class="admin-row"><span>Subtotal · ' +
        itemCount +
        " item" +
        (itemCount === 1 ? "" : "s") +
        "</span><span>" +
        esc(Kit.money(o.totals.subtotal)) +
        "</span></div>" +
        '<div class="admin-row"><span>Delivery</span><span>' +
        esc(Kit.money(o.totals.delivery)) +
        "</span></div>" +
        '<div class="admin-row admin-row--total"><strong>Total</strong><strong>' +
        esc(Kit.money(o.totals.total)) +
        "</strong></div>" +
        '<p class="admin-note">Cash on delivery</p></section>' +
        "</div></div>" +
        '<p style="margin-top:1.5rem"><a class="btn btn--outline" href="' +
        Kit.url("orders") +
        '">Back to orders</a></p>';

      const form = $("#status-form");
      if (!form) return;
      const apply = async (status) => {
        try {
          const { data } = await api.patch(
            "/admin/orders/" + encodeURIComponent(o.number) + "/status",
            { status, note: form.note.value },
          );
          Kit.toast(
            "Order status changed",
            "success",
            o.number +
              " is now " +
              Kit.statusLabel(status) +
              ". A customer order-status update email will be submitted.",
          );
          draw(data);
        } catch (error) {
          Kit.toast("Status not changed", "error", error.message);
        }
      };
      const askCancel = async () => {
        if (
          await Kit.confirm({
            title: "Cancel order " + o.number + "?",
            text: "This marks the order Cancelled and submits a status update email. Reserved stock is restored once only. Keep current status to dismiss.",
            confirm: "Cancel order",
            danger: true,
            keep: "Keep current status",
            keepFirst: true,
          })
        )
          apply("cancelled");
      };
      form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const status = form.status.value;
        if (status === "cancelled") {
          askCancel();
          return;
        }
        if (
          await Kit.confirm({
            title: "Confirm status change?",
            text:
              "Change " +
              o.number +
              " from " +
              Kit.statusLabel(o.status) +
              " to " +
              Kit.statusLabel(status) +
              "? The customer will receive an order-status update email. This is not a courier action.",
            confirm: "Confirm change",
            keep: "Keep current status",
            keepFirst: true,
          })
        )
          apply(status);
      });
      $("#cancel-order").addEventListener("click", askCancel);
    }
    load();
  };

  /* ==========================================================================
       Customer detail
       ========================================================================== */
  Kit.pages.customer = async () => {
    const app = $("#app");
    const id = Number(app.dataset.id);
    try {
      const { data: c } = await api.get("/admin/customers/" + id);
      const delivered = c.orders.filter((o) => o.status === "delivered").length;
      const note =
        c.orders_count === 0
          ? "No orders yet."
          : delivered === 0
            ? c.orders
                .map((o) => o.number + " is " + Kit.statusLabel(o.status))
                .slice(0, 2)
                .join(", ") + ", so it is not recorded sales."
            : delivered +
              " delivered order" +
              (delivered === 1 ? "" : "s") +
              " counted as sales.";
      app.innerHTML =
        '<h2 class="admin-h2" style="margin:1.5rem 0 0">' +
        esc(c.name) +
        "</h2>" +
        '<div class="admin-split admin-split--even">' +
        '<section class="card admin-panel"><h2>Account details</h2>' +
        '<div class="admin-row"><span>Email</span><span>' +
        esc(c.email) +
        "</span></div>" +
        '<div class="admin-row"><span>Phone: ' +
        esc(c.phone || "—") +
        "</span></div>" +
        '<div class="admin-row"><span>Orders</span><span>' +
        c.orders_count +
        "</span></div>" +
        '<div class="admin-row"><span>Delivered merchandise sales</span><span>' +
        esc(Kit.money(c.delivered_sales)) +
        "</span></div>" +
        '<p class="admin-note">' +
        esc(note) +
        "</p></section>" +
        '<section class="card admin-panel"><h2>Default delivery address</h2>' +
        (c.address.line1 || c.address.city
          ? '<p class="admin-line-text">' +
            esc(c.address.line1 || "") +
            '</p><p class="admin-line-text">' +
            esc([c.address.city, c.address.postal].filter(Boolean).join(" ")) +
            "</p>"
          : '<p class="admin-note">No default address saved.</p>') +
        '<p class="admin-note">Changes to account details are made by the customer.</p></section></div>' +
        '<h2 class="admin-h2" style="margin-top:2rem">Order history</h2>' +
        (c.orders.length
          ? Kit.table(
              [
                {
                  label: "Order",
                  cell: (o) => "<strong>" + esc(o.number) + "</strong>",
                },
                { label: "Placed", cell: (o) => esc(Kit.date(o.placed_at)) },
                { label: "Total", cell: (o) => esc(Kit.money(o.total)) },
                { label: "Status", cell: (o) => Kit.badge(o.status) },
                {
                  label: "Action",
                  cell: (o) =>
                    '<a class="row-link" href="' +
                    Kit.url("order?number=" + encodeURIComponent(o.number)) +
                    '">View order</a>',
                },
              ],
              c.orders,
            )
          : '<p class="admin-empty">This customer has not placed an order yet.</p>') +
        '<div class="admin-actions">' +
        (c.orders[0]
          ? '<a class="btn btn--primary" href="' +
            Kit.url("order?number=" + encodeURIComponent(c.orders[0].number)) +
            '">Open ' +
            esc(c.orders[0].number) +
            "</a>"
          : "") +
        '<a class="btn btn--outline" href="' +
        Kit.url("customers") +
        '">Back to customers</a></div>';
    } catch (error) {
      Kit.banner(
        app,
        "error",
        error.status === 404
          ? "That customer was not found."
          : "The customer could not be loaded. " + error.message,
      );
    }
  };

  /* ==========================================================================
       Reports (orders / sales / stock) + CSV export
       ========================================================================== */
  Kit.pages.reports = () => {
    const app = $("#app");
    const range = Kit.periodRange("this_month");
    let tab = ["orders", "sales", "stock"].includes(location.hash.slice(1))
      ? location.hash.slice(1)
      : "orders";
    const f = {
      from: range.from,
      to: range.to,
      status: "",
      category: "",
      stock: "all",
    };

    const tabs = () =>
      '<div class="admin-tabs" role="tablist">' +
      [
        ["orders", "Orders"],
        ["sales", "Sales"],
        ["stock", "Stock"],
      ]
        .map(
          ([k, l]) =>
            '<button type="button" role="tab" aria-selected="' +
            (tab === k) +
            '" class="btn ' +
            (tab === k ? "btn--primary" : "btn--outline") +
            '" data-tab="' +
            k +
            '">' +
            l +
            "</button>",
        )
        .join("") +
      "</div>";

    async function show() {
      location.hash = tab;
      app.innerHTML =
        tabs() + '<div id="report"><p class="admin-loading">Loading…</p></div>';
      $$("[data-tab]", app).forEach((b) =>
        b.addEventListener("click", () => {
          tab = b.dataset.tab;
          show();
        }),
      );
      const box = $("#report");
      try {
        if (tab === "stock") await stockTab(box);
        else await dateTab(box);
      } catch (error) {
        Kit.banner(
          box,
          "error",
          "The report could not be loaded. " + error.message,
          show,
        );
      }
    }

    // Reports show dates as "09 Oct 2026" (zero-padded day), as in the design.
    const rdate = (text) =>
      text
        ? new Date(String(text).replace(" ", "T")).toLocaleDateString("en-GB", {
            day: "2-digit",
            month: "short",
            year: "numeric",
          })
        : "";

    // Wording for the empty state, e.g. "01–10 November 2026".
    const rangeText = () => {
      const long = (d) =>
        new Date(d + "T00:00:00").toLocaleDateString("en-GB", {
          day: "2-digit",
          month: "long",
          year: "numeric",
        });
      if (f.from && f.to)
        return f.from.slice(0, 7) === f.to.slice(0, 7)
          ? long(f.from).slice(0, 2) + "–" + long(f.to)
          : long(f.from) + " – " + long(f.to);
      if (f.from) return "dates from " + long(f.from);
      if (f.to) return "dates up to " + long(f.to);
      return "the selected filters";
    };
    const emptyState = (sales) =>
      '<section class="report-empty"><h2>' +
      (sales
        ? "No delivered orders in this period"
        : "No orders in this period") +
      "</h2><p>There are no " +
      (sales ? "delivered" : "matching") +
      " orders for " +
      esc(rangeText()) +
      ". " +
      (sales
        ? "Change the date range."
        : "Change the date range or status filter.") +
      '</p><button type="button" class="btn btn--primary" id="reset-filters">Reset report filters</button></section>';
    const notice = (kind, title, text) =>
      '<div class="alert report-alert report-alert--' +
      kind +
      '" role="' +
      (kind === "error" ? "alert" : "status") +
      '"><p class="report-alert__title">' +
      esc(title) +
      "</p><p>" +
      esc(text) +
      "</p></div>";

    const filters = (extra, button = "Apply filters") =>
      '<form class="admin-toolbar" id="filters" novalidate>' +
      extra +
      '<button type="submit" class="btn btn--primary">' +
      button +
      "</button></form>";
    const dateFields = () =>
      '<div class="field"><label for="r-from">From date</label><input class="input" id="r-from" type="date" name="from" value="' +
      esc(f.from || "") +
      '"></div>' +
      '<div class="field"><label for="r-to">To date</label><input class="input" id="r-to" type="date" name="to" value="' +
      esc(f.to || "") +
      '"></div>';

    async function dateTab(box) {
      const sales = tab === "sales";
      const { data, meta } = await api.get("/admin/reports/" + tab, {
        from: f.from,
        to: f.to,
        status: sales ? "" : f.status,
      });
      const csvLabel = sales ? "Export sales CSV" : "Export orders CSV";

      box.innerHTML =
        '<p class="admin-note" style="font-size:.875rem">' +
        (sales
          ? "Recorded sales are based ONLY on Delivered orders. Operational reporting, not full accounting."
          : "Filter orders by date and recorded order status. Operational reporting, not full accounting.") +
        "</p>" +
        filters(
          dateFields() +
            '<div class="field"><label for="r-status">Order status</label><select class="select" id="r-status" name="status"' +
            (sales ? " disabled" : "") +
            ">" +
            (sales
              ? "<option>Delivered only</option>"
              : '<option value="">All statuses</option>' +
                ["pending", "confirmed", "shipped", "delivered", "cancelled"]
                  .map(
                    (s) =>
                      '<option value="' +
                      s +
                      '"' +
                      (f.status === s ? " selected" : "") +
                      ">" +
                      Kit.statusLabel(s) +
                      "</option>",
                  )
                  .join("")) +
            "</select></div>",
        ) +
        '<div id="export-status" aria-live="polite"></div>' +
        (data.length === 0
          ? emptyState(sales)
          : sales
            ? '<section class="card admin-panel" style="margin-top:var(--space-5)"><h2>Delivered sales summary</h2>' +
              '<div class="admin-row"><span>Delivered orders</span><span>' +
              meta.delivered_orders +
              "</span></div>" +
              '<div class="admin-row"><strong>Merchandise sales</strong><strong>' +
              esc(Kit.money(meta.merchandise_sales)) +
              "</strong></div>" +
              '<div class="admin-row"><span>Delivery fees (separate)</span><span>' +
              esc(Kit.money(meta.delivery_fees)) +
              "</span></div>" +
              '<div class="admin-row"><span>Delivered order totals</span><span>' +
              esc(Kit.money(meta.order_totals)) +
              "</span></div>" +
              '<p class="admin-note">No tax, expenses, profit or accounting ledger is modelled.</p></section>' +
              Kit.table(
                [
                  {
                    label: "Order",
                    cell: (r) => "<strong>" + esc(r.number) + "</strong>",
                  },
                  { label: "Customer", cell: (r) => esc(r.customer) },
                  {
                    label: "Delivered",
                    cell: (r) => esc(rdate(r.delivered_at)),
                  },
                  {
                    label: "Merchandise",
                    cell: (r) => esc(Kit.money(r.merchandise)),
                  },
                  {
                    label: "Delivery",
                    cell: (r) => esc(Kit.money(r.delivery)),
                  },
                  { label: "Total", cell: (r) => esc(Kit.money(r.total)) },
                ],
                data,
              )
            : '<section class="card admin-panel" style="margin-top:var(--space-5)"><h2>Orders summary</h2>' +
              '<div class="admin-row"><span>Matching orders</span><span>' +
              meta.matching +
              "</span></div>" +
              '<div class="admin-row"><span>Recorded order totals</span><span>' +
              esc(Kit.money(meta.recorded_total)) +
              "</span></div>" +
              '<p class="admin-note">Includes cancelled orders for reporting visibility. This total is NOT sales.</p></section>' +
              Kit.table(
                [
                  {
                    label: "Order",
                    cell: (r) => "<strong>" + esc(r.number) + "</strong>",
                  },
                  { label: "Customer", cell: (r) => esc(r.customer) },
                  { label: "Placed", cell: (r) => esc(rdate(r.placed_at)) },
                  { label: "Total", cell: (r) => esc(Kit.money(r.total)) },
                  { label: "Status", cell: (r) => Kit.badge(r.status) },
                  {
                    label: "Action",
                    cell: (r) =>
                      '<a class="row-link" href="' +
                      Kit.url("order?number=" + encodeURIComponent(r.number)) +
                      '">View order</a>',
                  },
                ],
                data,
              )) +
        '<p style="margin-top:var(--space-5)"><button type="button" class="btn btn--primary" id="export"' +
        (data.length === 0 ? " disabled" : "") +
        ">" +
        csvLabel +
        "</button></p>";

      $("#filters", box).addEventListener("submit", (event) => {
        event.preventDefault();
        const form = event.target;
        f.from = form.from.value;
        f.to = form.to.value;
        if (!sales) f.status = form.status.value;
        show();
      });
      const reset = $("#reset-filters", box);
      if (reset)
        reset.addEventListener("click", () => {
          Object.assign(f, { from: range.from, to: range.to, status: "" });
          show();
        });
      // Two steps, as in the design: prepare the file, then download it.
      let file = null;
      $("#export", box).addEventListener("click", async (event) => {
        const button = event.currentTarget;
        const status = $("#export-status", box);
        if (file) {
          const link = document.createElement("a");
          link.href = URL.createObjectURL(file.blob);
          link.download = file.name;
          document.body.append(link);
          link.click();
          link.remove();
          setTimeout(() => URL.revokeObjectURL(link.href), 1000);
          return;
        }
        button.disabled = true;
        try {
          const blob = await api.blob("/admin/reports/" + tab + ".csv", {
            from: f.from,
            to: f.to,
            status: sales ? "" : f.status,
          });
          file = {
            blob,
            name:
              tab + "_" + (f.from || "start") + "_" + (f.to || "end") + ".csv",
          };
          status.innerHTML = notice(
            "success",
            "CSV export ready",
            file.name +
              " · " +
              data.length +
              (data.length === 1 ? " record" : " records") +
              ". The file is ready to download.",
          );
          button.textContent = "Download " + tab + " CSV";
        } catch (error) {
          status.innerHTML = notice(
            "error",
            "Export couldn’t be prepared",
            "Your report filters are preserved. Retry CSV export. No report data has been changed.",
          );
          button.textContent = "Retry CSV export";
        } finally {
          button.disabled = false;
        }
      });
    }

    async function stockTab(box) {
      const [{ data: categories }, { data, meta }] = await Promise.all([
        api.get("/admin/categories"),
        api.get("/admin/reports/stock", {
          category: f.category,
          status: f.stock,
        }),
      ]);
      box.innerHTML =
        '<p class="admin-note" style="font-size:.875rem">Current product and variant stock. No CSV export for stock. Operational reporting, not full accounting.</p>' +
        filters(
          '<div class="field"><label for="r-cat">Product category</label><select class="select" id="r-cat" name="category"><option value="">All categories</option>' +
            categories
              .map(
                (c) =>
                  '<option value="' +
                  esc(c.name) +
                  '"' +
                  (f.category === c.name ? " selected" : "") +
                  ">" +
                  esc(c.name) +
                  "</option>",
              )
              .join("") +
            "</select></div>" +
            '<div class="field"><label for="r-stock">Stock status</label><select class="select" id="r-stock" name="stock">' +
            [
              ["all", "All stock levels"],
              ["in", "In stock"],
              ["low", "Low stock"],
              ["out", "Out of stock"],
            ]
              .map(
                ([v, l]) =>
                  '<option value="' +
                  v +
                  '"' +
                  (f.stock === v ? " selected" : "") +
                  ">" +
                  l +
                  "</option>",
              )
              .join("") +
            "</select></div>",
        ) +
        '<div class="stat-grid"><section class="card stat"><p class="stat__label">Available units</p><p class="stat__value">' +
        meta.units +
        "</p></section>" +
        '<section class="card stat"><p class="stat__label">Low-stock variants</p><p class="stat__value">' +
        meta.low +
        "</p></section>" +
        '<section class="card stat"><p class="stat__label">Sold-out variants</p><p class="stat__value">' +
        meta.out +
        "</p></section></div>" +
        (data.length
          ? Kit.table(
              [
                {
                  label: "Product",
                  cell: (r) => "<strong>" + esc(r.name) + "</strong>",
                },
                { label: "Available", cell: (r) => String(r.available) },
                {
                  label: "Attention",
                  cell: (r) =>
                    r.status === "in"
                      ? Kit.stockBadge("in")
                      : r.status === "low" && r.attention === "1 low"
                        ? Kit.stockBadge("low")
                        : esc(r.attention),
                },
              ],
              data,
            )
          : '<p class="admin-empty">No products match these filters.</p>') +
        '<p class="admin-note">Low stock: ' +
        (meta.notes.low.length ? esc(meta.notes.low.join(", ")) : "none") +
        ". Sold out: " +
        (meta.notes.out.length ? esc(meta.notes.out.join(", ")) : "none") +
        ".</p>";
      $("#filters", box).addEventListener("submit", (event) => {
        event.preventDefault();
        f.category = event.target.category.value;
        f.stock = event.target.stock.value;
        show();
      });
    }

    show();
  };

  /* ==========================================================================
       Settings
       ========================================================================== */
  Kit.pages.settings = async () => {
    const app = $("#app");
    let loaded;
    try {
      loaded = await api.get("/admin/settings");
    } catch (error) {
      Kit.banner(
        app,
        "error",
        "Settings could not be loaded. " + error.message,
        () => window.location.reload(),
      );
      return;
    }

    const s = loaded.data;
    const m = loaded.meta;
    app.innerHTML =
      '<div id="save-status" aria-live="polite"></div>' +
      '<form id="settings-form" novalidate><div class="admin-split admin-split--even">' +
      '<section class="card admin-panel"><h2>Basic store details</h2><div class="admin-form">' +
      '<div class="field"><label for="s-name">Store name <span aria-hidden="true">*</span></label><input class="input" id="s-name" name="store_name" maxlength="80" value="' +
      esc(s.store_name) +
      '"></div>' +
      '<div class="field"><label for="s-email">Support email <span aria-hidden="true">*</span></label><input class="input" id="s-email" name="support_email" type="email" maxlength="190" value="' +
      esc(s.support_email) +
      '"></div>' +
      '<div class="field"><label for="s-phone">Store phone</label><input class="input" id="s-phone" name="support_phone" maxlength="30" value="' +
      esc(s.support_phone) +
      '"></div>' +
      '<div class="field"><label for="s-address">Store address</label><textarea class="textarea" id="s-address" name="store_address" rows="3" maxlength="300">' +
      esc(s.store_address) +
      "</textarea></div>" +
      '<div class="field"><label for="s-currency">Currency</label><select class="select" id="s-currency" name="currency">' +
      m.currencies
        .map(
          (c) =>
            "<option" +
            (c === s.currency ? " selected" : "") +
            ">" +
            esc(c) +
            "</option>",
        )
        .join("") +
      "</select></div>" +
      '<div class="field"><label for="s-fee">Delivery fee</label><input class="input" id="s-fee" name="delivery_fee" type="number" min="0" step="0.01" inputmode="decimal" value="' +
      esc(s.delivery_fee) +
      '"><p class="field-hint">A single flat fee for every order. Delivery rules still need the client\'s approval.</p></div>' +
      "</div></section>" +
      '<div class="admin-stack"><section class="card admin-panel"><h2>Notification recipients</h2><div class="admin-form">' +
      '<div class="field"><label for="s-alert">Administrator order-alert recipients <span aria-hidden="true">*</span></label><input class="input" id="s-alert" name="alert_recipients" value="' +
      esc(s.alert_recipients) +
      '" placeholder="orders@example.com"><p class="field-hint">Separate several addresses with commas.</p></div>' +
      '<div class="field"><label for="s-extra">Additional recipients (optional)</label><input class="input" id="s-extra" name="alert_extra" value="' +
      esc(s.alert_extra) +
      '"></div>' +
      '<label class="check"><input type="checkbox" name="alert_enabled"' +
      (s.alert_enabled === "1" ? " checked" : "") +
      "><span>New-order administrator alert enabled</span></label>" +
      '<p class="admin-note" style="font-size:.875rem">Customer e-mails use the order or account e-mail. Four automated e-mail types only: order confirmation, admin new-order alert, order-status update, password reset.</p>' +
      "</div></section>" +
      (m.mail_configured
        ? '<div class="admin-note-box admin-note-box--ok"><strong>E-mail is switched on</strong>Messages are sent through the SMTP account in <code>' +
          esc(m.mail_file) +
          "</code>. SMTP does not guarantee inbox delivery. Provider credentials are deployment secrets and are never entered here.</div>"
        : '<div class="admin-note-box"><strong>SMTP provider setup required</strong>E-mail is not set up yet, so no e-mail is sent. Create <code>' +
          esc(m.mail_file) +
          "</code> (copy <code>config/mail.example.php</code>) with the SMTP details from your provider. Provider credentials are deployment secrets and are never entered here.</div>") +
      "</div></div>" +
      '<div class="admin-actions"><button type="submit" class="btn btn--primary">Save settings</button><button type="button" class="btn btn--outline" id="discard">Discard changes</button></div></form>';

    const form = $("#settings-form");
    $("#discard").addEventListener("click", () => window.location.reload());
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      Kit.clearErrors(form);
      const status = $("#save-status");
      status.innerHTML = "";
      const body = {};
      [
        "store_name",
        "support_email",
        "support_phone",
        "store_address",
        "currency",
        "delivery_fee",
        "alert_recipients",
        "alert_extra",
      ].forEach((k) => {
        body[k] = form[k].value;
      });
      body.alert_enabled = form.alert_enabled.checked;
      try {
        await api.patch("/admin/settings", body);
        Kit.banner(
          status,
          "success",
          "Settings saved. The store uses them straight away.",
        );
        Kit.toast("Settings saved", "success");
      } catch (error) {
        Kit.banner(
          status,
          "error",
          "Settings could not be saved. " +
            (error.fields && Object.keys(error.fields).length
              ? "Check the highlighted fields."
              : error.message),
        );
        Kit.fieldErrors(form, error.fields);
      }
    });
  };
})();
