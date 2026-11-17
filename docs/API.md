# API

Every endpoint lives under `/api/`. The `.htaccess` file sends those requests to `api/index.php`, and the routes are in `api/routes/`.

## Responses

Success:

```json
{ "ok": true, "data": { }, "meta": { } }
```

Failure:

```json
{ "ok": false, "error": { "code": "validation_failed", "message": "…", "fields": { "email": "…" } } }
```

## CSRF

Unsafe requests (POST, PATCH, PUT, DELETE) must send the token in the `X-CSRF-Token` header. Get it from `GET /api/session`, or from `<meta name="csrf-token">` on any page. The front end sends PATCH and DELETE as POST with `X-HTTP-Method-Override`.

## Shop (`api/routes/shop.php`)

| Method | Path | Body / query |
|---|---|---|
| GET | `/api/session` | Signed-in user, CSRF token, bag size |
| GET | `/api/products` | `q`, `category[]`, `size[]`, `color[]`, `stock=1`, `sort=recommended\|price_asc\|price_desc\|name`, `page`, `per_page` |
| GET | `/api/products/{slug}` | |
| GET | `/api/categories` | |
| GET | `/api/cart` | |
| POST | `/api/cart/items` | `{ product_id \| slug, color, size, qty }` |
| PATCH | `/api/cart/items` | `{ key, qty }` |
| DELETE | `/api/cart/items` | `{ key }` |
| POST | `/api/checkout` | `{ name, email, phone, line1, line2?, city, postal, notes?, payment_method?, terms }` |
| POST | `/api/contact` | `{ name, email, order_ref?, message }` |

## Account (`api/routes/account.php`)

| Method | Path | Body / query |
|---|---|---|
| POST | `/api/auth/register` | `{ name, email, password, confirm, terms, next? }` |
| POST | `/api/auth/login` | `{ email, password, next? }` |
| POST | `/api/auth/logout` | |
| POST | `/api/auth/forgot` | `{ email }`. Always gives the same answer, whether or not the account exists. |
| POST | `/api/auth/reset` | `{ token, password, confirm }` |
| GET | `/api/account` | |
| PATCH | `/api/account/profile` | `{ name, email, phone? }` |
| PATCH | `/api/account/address` | `{ line1?, city?, postal? }` |
| GET | `/api/orders` | The signed-in customer's orders, newest first |
| GET | `/api/orders/{number}` | The owner, or the visitor who has just placed it |

## Admin (`api/routes/admin.php`)

Admin endpoints need an admin session. Log in first with `POST /api/admin/login`.

| Method | Path | Body / query |
|---|---|---|
| POST | `/api/admin/login` | `{ email, password }` |
| GET | `/api/admin/me` | |
| GET | `/api/admin/dashboard` | `period=this_month\|last_30\|last_month\|all` |
| GET | `/api/admin/products` | `q`, `category`, `page` |
| GET | `/api/admin/products/{id}` | |
| POST | `/api/admin/products` | `{ name, sku, category, price, description, published, image_alt?, variants: [{color, size, stock}] }` |
| PATCH | `/api/admin/products/{id}` | Same fields as create |
| POST | `/api/admin/products/{id}/images` | Multipart, field `image` |
| PATCH | `/api/admin/products/{id}/images` | `{ order: [path, …] }`. The first path is the main picture. |
| DELETE | `/api/admin/products/{id}/images` | `{ path }` |
| GET | `/api/admin/variants` | `q`, `status=all\|in\|low\|out`, `page` |
| PATCH | `/api/admin/variants` | `{ product_id, color, size, stock }`. Returns `product_total` too. |
| GET | `/api/admin/categories` | `q` |
| POST | `/api/admin/categories` | `{ name, slug?, description?, visible }` |
| PATCH | `/api/admin/categories/{id}` | Renaming also renames the category on its products |
| DELETE | `/api/admin/categories/{id}` | Only when no product uses it |
| GET | `/api/admin/orders` | `q`, `status`, `from`, `to`, `page` |
| GET | `/api/admin/orders/{number}` | |
| PATCH | `/api/admin/orders/{number}/status` | `{ status, note? }` |
| GET | `/api/admin/customers` | `q`, `page` |
| GET | `/api/admin/customers/{id}` | |
| GET | `/api/admin/reports/orders` | `from`, `to`, `status`. Also `orders.csv` |
| GET | `/api/admin/reports/sales` | `from`, `to`. Delivered orders only. Also `sales.csv` |
| GET | `/api/admin/reports/stock` | `category`, `status` |
| GET | `/api/admin/settings` | |
| PATCH | `/api/admin/settings` | Store settings |
