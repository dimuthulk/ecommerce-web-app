/**
 * Small client for the site's JSON API (see api/ and docs/API.md).
 *
 *   const { data, meta } = await api.get('/products', { category: ['Shirts'], page: 2 });
 *   await api.post('/cart/items', { slug: 'essential-cotton-tee', color: 'Chalk', size: 'M', qty: 1 });
 *   await api.patch('/admin/settings', {...});     await api.del('/cart/items', { key });
 *
 * Failed calls throw an ApiError with .status, .code, .message and .fields (errors per form field).
 * The CSRF token is read from <meta name="csrf-token">; PATCH/DELETE go out as POST + X-HTTP-Method-Override
 * so they also work on hosts that block those methods.
 */
window.api = (() => {
    const meta = (name) => (document.querySelector('meta[name="' + name + '"]') || {}).content || '';
    let csrf = meta('csrf-token');
    const base = meta('api-base') || '/api';

    class ApiError extends Error {
        constructor(status, code, message, fields) {
            super(message);
            this.name = 'ApiError';
            this.status = status;
            this.code = code;
            this.fields = fields || {};
        }
    }

    const query = (params) => {
        const search = new URLSearchParams();
        Object.entries(params || {}).forEach(([key, value]) => {
            if (value === undefined || value === null || value === '') return;
            if (Array.isArray(value)) value.forEach((v) => search.append(key + '[]', v));
            else search.append(key, value);
        });
        const text = search.toString();
        return text ? '?' + text : '';
    };

    async function send(method, path, { body, params, form } = {}) {
        const headers = { Accept: 'application/json' };
        const options = { method: 'GET', credentials: 'same-origin', headers };

        if (method !== 'GET') {
            options.method = 'POST';
            headers['X-CSRF-Token'] = csrf;
            if (method !== 'POST') headers['X-HTTP-Method-Override'] = method;
            if (form) {
                options.body = form;                                   // FormData (file uploads): the browser sets the type
            } else if (body !== undefined) {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(body);
            }
        }

        let response;
        try {
            response = await fetch(base + path + query(params), options);
        } catch (networkError) {
            throw new ApiError(0, 'network', 'The server could not be reached. Check your connection and try again.');
        }
        return response;
    }

    async function request(method, path, opts) {
        const response = await send(method, path, opts);
        let json = null;
        try { json = await response.json(); } catch (e) { /* not JSON */ }

        if (!response.ok || !json || json.ok === false) {
            const error = (json && json.error) || {};
            throw new ApiError(response.status, error.code || 'server_error', error.message || 'Something went wrong. Please try again.', error.fields);
        }
        return json;                                                    // { ok, data, meta }
    }

    return {
        ApiError,
        base,
        get: (path, params) => request('GET', path, { params }),
        post: (path, body) => request('POST', path, { body }),
        patch: (path, body) => request('PATCH', path, { body }),
        del: (path, body) => request('DELETE', path, { body }),
        upload: (path, form) => request('POST', path, { form }),
        /** Download a file (CSV) and return it as a Blob. */
        async blob(path, params) {
            const response = await send('GET', path, { params });
            if (!response.ok) {
                let message = 'The export failed. Please try again.';
                try { message = (await response.json()).error.message || message; } catch (e) { /* keep default */ }
                throw new ApiError(response.status, 'export_failed', message);
            }
            return response.blob();
        },
        setCsrf(token) { csrf = token; },
    };
})();
