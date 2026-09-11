# Ecommerce API

Laravel 7 REST API for an ecommerce storefront and its administration, inventory, procurement, returns, and finance operations.

## Available features

- Token-based login with Sanctum and separate **admin** and **user** access rules.
- Public catalog: sections, categories, products, discounts, bundles, and blogs.
- Customer addresses, cart management, coupons, checkout, payment callbacks, order history, and cancellations.
- Admin CRUD for catalog content, users, reviews, coupons, and blogs.
- Procurement workflow: requisition, acceptance, receiving, on-hand confirmation, payments, stock receipts, warehouse stock, and inventory adjustments.
- Returns, refunds, order tracking, company accounts, transactions, and account history.

## Run locally

```bash
composer install
cp .env.example .env
php artisan key:generate
# Set DB_* values in .env, then:
php artisan migrate
php artisan serve
```

The examples below assume the server is on `http://127.0.0.1:8000`.

## cURL setup

```bash
export BASE='http://127.0.0.1:8000/api'
export ADMIN_TOKEN='replace-with-an-admin-token'
export USER_TOKEN='replace-with-a-user-token'
export JSON='Content-Type: application/json'
```

In this document, append the shown admin/user authorization header to each command:

```bash
-H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"  # admin endpoint
-H "Authorization: Bearer $USER_TOKEN" -H "$JSON"   # customer endpoint
```

Replace path variables such as `:id`, `:product_id`, and `:order_id` with real integer IDs. Multipart endpoints use `-F` rather than `-H "$JSON"`.

## Response conventions

Most endpoints return the following envelope; `data` is either an object, an array, or Laravel pagination data.

```json
{"isExecuted": true, "message": "…", "data": {}}
```

Successful creates generally return **201**; reads/updates/deletes generally return **200**. A missing record returns **404**, validation errors return **422** (`{"message":"The given data was invalid.","errors":{…}}`), and missing/invalid credentials return **401**. An authenticated token with the wrong role returns **403**.

For each command below, the **Expected** column is the successful response message or response type. Therefore, a `200 envelope` means the envelope above with HTTP 200.

Laravel registers **78 API paths and 122 method/path combinations**. Every path is documented below. For update paths that intentionally accept multiple HTTP verbs, this table documents the exact alternatives; use the same URL, headers, payload, and expected response as the `PATCH` command in the relevant section.

Every `GET` endpoint is also registered as `HEAD`; for its HEAD cURL use `curl -sSI` with the GET URL and the same authorization header. It returns the same status and headers as GET, with no response body.

| Path | Registered update methods | cURL alternative |
|---|---|---|
| `/admin/categories/:id` | `PUT`, `PATCH`, `POST` | replace `-X PATCH` with `-X PUT` or `-X POST` |
| `/admin/subcategories/:id` | `PUT`, `PATCH` | replace `-X PATCH` with `-X PUT` |
| `/admin/sections/:id` | `PUT`, `PATCH` | replace `-X PATCH` with `-X PUT` |
| `/admin/section-products/:id` | `PUT`, `PATCH` | replace `-X PATCH` with `-X PUT` |
| `/admin/procurement-payments/:id` | `PUT`, `PATCH` | replace `-X PATCH` with `-X PUT` |
| `/admin/coupons/:id` | `PUT`, `PATCH`, `POST` | replace `-X PATCH` with `-X PUT` or `-X POST` |
| `/admin/products/:id` | `PUT`, `PATCH`, `POST` | replace `-X PATCH` with `-X PUT` or `-X POST` |
| `/admin/product-discounts/:id` | `PUT`, `PATCH`, `POST` | replace `-X PATCH` with `-X PUT` or `-X POST` |
| `/admin/product-bundles/:id` | `PUT`, `PATCH` | replace `-X PATCH` with `-X PUT` |
| `/admin/product-reviews/:id` | `PUT`, `PATCH` | replace `-X PATCH` with `-X PUT` |
| `/admin/company-accounts/:id` | `PUT`, `PATCH` | replace `-X PATCH` with `-X PUT` |

## Authentication

| Endpoint | cURL | Expected |
|---|---|---|
| `POST /login` | `curl -sS -X POST "$BASE/login" -H "$JSON" -d '{"email":"admin@example.com","password":"password"}'` | `200 {"access_token":"…","token_type":"Bearer","expires_in":null,"user":{…}}` |

Use the returned `access_token` in `ADMIN_TOKEN` or `USER_TOKEN`. The user must have the relevant `role` for the requested group.

## Public storefront endpoints

| Endpoint | cURL | Expected |
|---|---|---|
| `GET /users/products` | `curl -sS "$BASE/users/products?perPage=10&page=1&search=" -H "$JSON"` | `200`, products/sections envelope |
| `GET /users/products/:id` | `curl -sS "$BASE/users/products/:product_id" -H "$JSON"` | `200`, product-detail envelope; `404` when absent |
| `GET /users/category/products/:id` | `curl -sS "$BASE/users/category/products/:category_id?perPage=10&page=1" -H "$JSON"` | `200`, category products envelope |
| `GET /users/category` | `curl -sS "$BASE/users/category" -H "$JSON"` | `200`, categories envelope |
| `GET /users/categories` | `curl -sS "$BASE/users/categories" -H "$JSON"` | `200`, categories envelope (alias) |
| `GET /users/blogs` | `curl -sS "$BASE/users/blogs?perPage=10&page=1&search=" -H "$JSON"` | `200`, blogs envelope |
| `GET /users/blogs/:id` | `curl -sS "$BASE/users/blogs/:blog_id" -H "$JSON"` | `200`, blog envelope; `404` when absent |

## Customer endpoints

All endpoints in this section require `Authorization: Bearer $USER_TOKEN`.

| Endpoint | cURL | Expected |
|---|---|---|
| `POST /users/add/cart` | `curl -sS -X POST "$BASE/users/add/cart" -H "Authorization: Bearer $USER_TOKEN" -H "$JSON" -d '[{"product_id":1,"quantity":2}]'` | `200`, cart-added envelope |
| `POST /users/update/cart` | `curl -sS -X POST "$BASE/users/update/cart" -H "Authorization: Bearer $USER_TOKEN" -H "$JSON" -d '{"id":1,"quantity":3}'` | `200`, cart-updated envelope |
| `DELETE /users/remove/cart/:id` | `curl -sS -X DELETE "$BASE/users/remove/cart/:cart_item_id" -H "Authorization: Bearer $USER_TOKEN" -H "$JSON"` | `200`, cart-removed envelope |
| `GET /users/mycart` | `curl -sS "$BASE/users/mycart" -H "Authorization: Bearer $USER_TOKEN" -H "$JSON"` | `200`, customer cart envelope |
| `GET /users/applycoupon/:id` | `curl -sS "$BASE/users/applycoupon/:coupon_id" -H "Authorization: Bearer $USER_TOKEN" -H "$JSON"` | `200`, applied-coupon envelope |
| `GET /users/addresses` | `curl -sS "$BASE/users/addresses" -H "Authorization: Bearer $USER_TOKEN" -H "$JSON"` | `200`, saved-addresses envelope |
| `POST /users/addresses` | `curl -sS -X POST "$BASE/users/addresses" -H "Authorization: Bearer $USER_TOKEN" -H "$JSON" -d '{"address_line1":"42 Market St","city":"Dhaka","state":"Dhaka","postal_code":"1205","country":"BD","phone":"01700000000","is_primary":true}'` | `201`, address-created envelope |
| `POST /users/order` | `curl -sS -X POST "$BASE/users/order" -H "Authorization: Bearer $USER_TOKEN" -H "$JSON" -d '{"payment_method":"cash","totalAmount":199.98,"products":[{"product_id":1,"quantity":2,"price":99.99}],"userInformation":{"name":"Jane Doe","address":"42 Market St","city":"Dhaka","state":"Dhaka","zip":"1205","phone":"01700000000","country":"BD","email":"jane@example.com"}}'` | `200`, order/payment response; gateway methods may return a redirect URL |
| `GET /users/orders` | `curl -sS "$BASE/users/orders" -H "Authorization: Bearer $USER_TOKEN" -H "$JSON"` | `200`, customer orders envelope |
| `GET /users/orders/:id` | `curl -sS "$BASE/users/orders/:order_id" -H "Authorization: Bearer $USER_TOKEN" -H "$JSON"` | `200`, order detail envelope; `404` when absent/not owned |
| `POST /users/orders/:id/cancel` | `curl -sS -X POST "$BASE/users/orders/:order_id/cancel" -H "Authorization: Bearer $USER_TOKEN" -H "$JSON"` | `200`, cancelled-order envelope; `422` if not cancellable |
| `POST /users/order/success` | `curl -sS -X POST "$BASE/users/order/success" -H "$JSON" -d @gateway-success.json` | payment-gateway callback response |
| `POST /users/order/fail` | `curl -sS -X POST "$BASE/users/order/fail" -H "$JSON" -d @gateway-fail.json` | payment-gateway callback response |
| `POST /users/order/cancel` | `curl -sS -X POST "$BASE/users/order/cancel" -H "$JSON" -d @gateway-cancel.json` | payment-gateway callback response |

`POST /users/order` also accepts an existing `address_id`; when it is omitted, the address fields in `userInformation` are required. Optional fields are `save_address`, `is_primary_address`, `userInformation.apartment`, and `address_id`.

## Admin catalog and content

All endpoints in the remaining sections require `Authorization: Bearer $ADMIN_TOKEN`.

### Categories, subcategories, and sections

| Endpoint | cURL | Expected |
|---|---|---|
| `GET /admin/categories` | `curl -sS "$BASE/admin/categories?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, `Categories fetched successfully` |
| `POST /admin/categories` | `curl -sS -X POST "$BASE/admin/categories" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"name":"Electronics","description":"Devices"}'` | `201`, `Category created successfully` |
| `GET /admin/categories/:id` | `curl -sS "$BASE/admin/categories/:category_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, `Category fetched successfully` |
| `PATCH /admin/categories/:id` | `curl -sS -X PATCH "$BASE/admin/categories/:category_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"name":"Electronics","description":"Updated"}'` | `200`, `Category updated successfully` |
| `DELETE /admin/categories/:id` | `curl -sS -X DELETE "$BASE/admin/categories/:category_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, `Category deleted successfully` |
| `GET /admin/subcategories/options` | `curl -sS "$BASE/admin/subcategories/options" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, subcategory options envelope |
| `GET /admin/subcategories` | `curl -sS "$BASE/admin/subcategories?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, subcategories envelope |
| `POST /admin/subcategories` | `curl -sS -X POST "$BASE/admin/subcategories" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"category_id":1,"name":"Phones","description":"Mobile phones"}'` | `201`, subcategory-created envelope |
| `GET /admin/subcategories/:id` | `curl -sS "$BASE/admin/subcategories/:subcategory_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, subcategory envelope |
| `PATCH /admin/subcategories/:id` | `curl -sS -X PATCH "$BASE/admin/subcategories/:subcategory_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"category_id":1,"name":"Phones"}'` | `200`, subcategory-updated envelope |
| `DELETE /admin/subcategories/:id` | `curl -sS -X DELETE "$BASE/admin/subcategories/:subcategory_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, subcategory-deleted envelope |
| `GET /admin/sections` | `curl -sS "$BASE/admin/sections?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, sections envelope |
| `POST /admin/sections` | `curl -sS -X POST "$BASE/admin/sections" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"name":"Featured","display_order":1,"is_active":true}'` | `201`, section-created envelope |
| `GET /admin/sections/:id` | `curl -sS "$BASE/admin/sections/:section_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, section envelope |
| `PATCH /admin/sections/:id` | `curl -sS -X PATCH "$BASE/admin/sections/:section_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"name":"Featured","display_order":1,"is_active":true}'` | `200`, section-updated envelope |
| `DELETE /admin/sections/:id` | `curl -sS -X DELETE "$BASE/admin/sections/:section_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, section-deleted envelope |

### Products, discounts, bundles, section products, and reviews

| Endpoint | cURL | Expected |
|---|---|---|
| `GET /admin/products/options` | `curl -sS "$BASE/admin/products/options" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, product options envelope |
| `GET /admin/products` | `curl -sS "$BASE/admin/products?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, products envelope |
| `POST /admin/products` | `curl -sS -X POST "$BASE/admin/products" -H "Authorization: Bearer $ADMIN_TOKEN" -F 'name=Phone' -F 'price=999' -F 'is_active=1' -F 'category_id=1' -F 'subcategory_id=1' -F 'stock_quantity=25' -F 'images[]=@/absolute/path/phone.jpg'` | `201`, product-created envelope |
| `GET /admin/products/:id` | `curl -sS "$BASE/admin/products/:product_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, product envelope |
| `PATCH /admin/products/:id` | `curl -sS -X PATCH "$BASE/admin/products/:product_id" -H "Authorization: Bearer $ADMIN_TOKEN" -F 'name=Phone' -F 'price=899' -F 'is_active=1' -F 'category_id=1' -F 'subcategory_id=1' -F 'stock_quantity=25'` | `200`, product-updated envelope |
| `DELETE /admin/products/:id` | `curl -sS -X DELETE "$BASE/admin/products/:product_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, product-deleted envelope |
| `GET /admin/product-discounts/options` | `curl -sS "$BASE/admin/product-discounts/options" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, discount options envelope |
| `GET /admin/product-discounts` | `curl -sS "$BASE/admin/product-discounts?perPage=10&page=1" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, discounts envelope |
| `POST /admin/product-discounts` | `curl -sS -X POST "$BASE/admin/product-discounts" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"product_id":1,"discount_type":"percentage","discount_value":10,"start_date":"2026-01-01","end_date":"2026-12-31"}'` | `201`, discount-created envelope |
| `GET /admin/product-discounts/:id` | `curl -sS "$BASE/admin/product-discounts/:discount_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, discount envelope |
| `PATCH /admin/product-discounts/:id` | `curl -sS -X PATCH "$BASE/admin/product-discounts/:discount_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"product_id":1,"discount_type":"flat","discount_value":50}'` | `200`, discount-updated envelope |
| `DELETE /admin/product-discounts/:id` | `curl -sS -X DELETE "$BASE/admin/product-discounts/:discount_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, discount-deleted envelope |
| `GET /admin/product-bundles` | `curl -sS "$BASE/admin/product-bundles?perPage=10&page=1" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, bundles envelope |
| `POST /admin/product-bundles` | `curl -sS -X POST "$BASE/admin/product-bundles" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"name":"Starter Pack","description":"Bundle","price":150,"discount_price":125,"is_active":true}'` | `201`, bundle-created envelope |
| `GET /admin/product-bundles/:id` | `curl -sS "$BASE/admin/product-bundles/:bundle_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, bundle envelope |
| `PATCH /admin/product-bundles/:id` | `curl -sS -X PATCH "$BASE/admin/product-bundles/:bundle_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"name":"Starter Pack","price":150}'` | `200`, bundle-updated envelope |
| `DELETE /admin/product-bundles/:id` | `curl -sS -X DELETE "$BASE/admin/product-bundles/:bundle_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, bundle-deleted envelope |
| `GET /admin/section-products/options` | `curl -sS "$BASE/admin/section-products/options" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, section-product options envelope |
| `GET /admin/section-products` | `curl -sS "$BASE/admin/section-products?perPage=10&page=1" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, section products envelope |
| `POST /admin/section-products` | `curl -sS -X POST "$BASE/admin/section-products" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"section_id":1,"product_id":1,"bundle_id":1,"display_order":1}'` | `201`, assignment-created envelope |
| `GET /admin/section-products/:id` | `curl -sS "$BASE/admin/section-products/:section_product_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, assignment envelope |
| `PATCH /admin/section-products/:id` | `curl -sS -X PATCH "$BASE/admin/section-products/:section_product_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"section_id":1,"product_id":1,"display_order":2}'` | `200`, assignment-updated envelope |
| `DELETE /admin/section-products/:id` | `curl -sS -X DELETE "$BASE/admin/section-products/:section_product_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, assignment-deleted envelope |
| `GET /admin/product-reviews/options` | `curl -sS "$BASE/admin/product-reviews/options" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, review options envelope |
| `GET /admin/product-reviews` | `curl -sS "$BASE/admin/product-reviews?perPage=10&page=1" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, reviews envelope |
| `POST /admin/product-reviews` | `curl -sS -X POST "$BASE/admin/product-reviews" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"user_id":2,"product_id":1,"rating":5,"review":"Excellent"}'` | `201`, review-created envelope |
| `GET /admin/product-reviews/:id` | `curl -sS "$BASE/admin/product-reviews/:review_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, review envelope |
| `PATCH /admin/product-reviews/:id` | `curl -sS -X PATCH "$BASE/admin/product-reviews/:review_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"user_id":2,"product_id":1,"rating":4,"review":"Good"}'` | `200`, review-updated envelope |
| `DELETE /admin/product-reviews/:id` | `curl -sS -X DELETE "$BASE/admin/product-reviews/:review_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, review-deleted envelope |

Product optional fields: `sku`, `description`, `warehouse_location`, `discount_type` (`flat`/`percentage`), `discount_value`, `discount_start_date`, `discount_end_date`, `section_ids[]`, and `display_order`. Images accept JPG/JPEG/PNG/WebP, up to 5 MB each (maximum 10).

### Coupons, users, blogs, and orders

| Endpoint | cURL | Expected |
|---|---|---|
| `GET /admin/coupons` | `curl -sS "$BASE/admin/coupons?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, coupons envelope |
| `POST /admin/coupons` | `curl -sS -X POST "$BASE/admin/coupons" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"code":"WELCOME10","discount_type":"percentage","discount_value":10,"max_usage":100}'` | `201`, coupon-created envelope |
| `GET /admin/coupons/:id` | `curl -sS "$BASE/admin/coupons/:coupon_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, coupon envelope |
| `PATCH /admin/coupons/:id` | `curl -sS -X PATCH "$BASE/admin/coupons/:coupon_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"code":"WELCOME10","discount_type":"percentage","discount_value":15}'` | `200`, coupon-updated envelope |
| `DELETE /admin/coupons/:id` | `curl -sS -X DELETE "$BASE/admin/coupons/:coupon_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, coupon-deleted envelope |
| `GET /admin/users` | `curl -sS "$BASE/admin/users?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, users envelope |
| `POST /admin/users` | `curl -sS -X POST "$BASE/admin/users" -H "Authorization: Bearer $ADMIN_TOKEN" -F 'name=Staff User' -F 'email=staff@example.com' -F 'role=users' -F 'password=password123'` | `201`, user-created envelope |
| `GET /admin/users/:id` | `curl -sS "$BASE/admin/users/:user_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, user envelope |
| `POST /admin/users/:id` | `curl -sS -X POST "$BASE/admin/users/:user_id" -H "Authorization: Bearer $ADMIN_TOKEN" -F 'name=Staff User' -F 'role=users'` | `200`, user-updated envelope |
| `GET /admin/blogs` | `curl -sS "$BASE/admin/blogs?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, blogs envelope |
| `POST /admin/blogs` | `curl -sS -X POST "$BASE/admin/blogs" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"title":"Launch","content":"Full article","short_description":"Summary","category":"News","fa_icon":"fa-star","author_name":"Admin","published_date":"2026-09-11","isPublished":true}'` | `201`, blog-created envelope |
| `GET /admin/blogs/:id` | `curl -sS "$BASE/admin/blogs/:blog_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, blog envelope |
| `POST /admin/blogs/:id` | `curl -sS -X POST "$BASE/admin/blogs/:blog_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"title":"Updated launch"}'` | `200`, blog-updated envelope |
| `GET /admin/orders` | `curl -sS "$BASE/admin/orders?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, orders envelope |
| `GET /admin/orders/:id` | `curl -sS "$BASE/admin/orders/:order_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, order envelope |
| `POST /admin/orders/:id/tracking` | `curl -sS -X POST "$BASE/admin/orders/:order_id/tracking" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"status":"shipped","location":"Dhaka hub"}'` | `200`, tracking-updated envelope |

Coupon dates are optional ISO dates; `discount_type` is `flat` or `percentage`. User images use the same JPG/JPEG/PNG/WebP 5 MB constraint. Blog creation requires all fields shown; updates accept fields selectively.

## Administration: finance, returns, and refunds

| Endpoint | cURL | Expected |
|---|---|---|
| `GET /admin/transactions` | `curl -sS "$BASE/admin/transactions?perPage=10&page=1&from_date=2026-01-01&to_date=2026-12-31" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, transactions envelope |
| `GET /admin/transactions/:id` | `curl -sS "$BASE/admin/transactions/:transaction_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, transaction envelope |
| `GET /admin/company-accounts` | `curl -sS "$BASE/admin/company-accounts?perPage=10&page=1" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, accounts envelope |
| `POST /admin/company-accounts` | `curl -sS -X POST "$BASE/admin/company-accounts" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"account_name":"Cash","account_number":"CASH-001","amount":10000,"type":"cash"}'` | `201`, account-created envelope |
| `GET /admin/company-accounts/summary` | `curl -sS "$BASE/admin/company-accounts/summary" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, account-summary envelope |
| `GET /admin/company-accounts/:id` | `curl -sS "$BASE/admin/company-accounts/:account_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, account envelope |
| `PATCH /admin/company-accounts/:id` | `curl -sS -X PATCH "$BASE/admin/company-accounts/:account_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"account_name":"Cash","account_number":"CASH-001","amount":9500,"type":"cash"}'` | `200`, account-updated envelope |
| `DELETE /admin/company-accounts/:id` | `curl -sS -X DELETE "$BASE/admin/company-accounts/:account_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, account-deleted envelope |
| `GET /admin/account-history` | `curl -sS "$BASE/admin/account-history?perPage=10&page=1&company_account_id=1" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, account-history envelope |
| `GET /admin/returns` | `curl -sS "$BASE/admin/returns?perPage=10&page=1&status=requested" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, returns envelope |
| `POST /admin/returns` | `curl -sS -X POST "$BASE/admin/returns" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"order_id":1,"product_id":1,"reason":"Damaged","status":"requested","refund_amount":99.99}'` | `201`, return-created envelope |
| `GET /admin/returns/:id` | `curl -sS "$BASE/admin/returns/:return_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, return detail envelope |
| `PATCH /admin/returns/:id` | `curl -sS -X PATCH "$BASE/admin/returns/:return_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"order_id":1,"product_id":1,"status":"approved","refund_amount":99.99}'` | `200`, return-updated envelope |
| `DELETE /admin/returns/:id` | `curl -sS -X DELETE "$BASE/admin/returns/:return_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, return-deleted envelope |
| `GET /admin/refunds` | `curl -sS "$BASE/admin/refunds?perPage=10&page=1" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, refunds envelope |
| `GET /admin/refunds/:id` | `curl -sS "$BASE/admin/refunds/:refund_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, refund detail envelope; `404` when absent |

Return status must be one of `requested`, `approved`, `rejected`, or `refunded`. Tracking status must be `pending`, `processing`, `shipped`, `delivered`, or `cancelled`.

## Administration: procurement and inventory

| Endpoint | cURL | Expected |
|---|---|---|
| `GET /admin/requisitions/options` | `curl -sS "$BASE/admin/requisitions/options" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, product options envelope |
| `GET /admin/requisitions` | `curl -sS "$BASE/admin/requisitions?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, requisitions envelope |
| `POST /admin/requisitions` | `curl -sS -X POST "$BASE/admin/requisitions" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"requested_by":"Purchasing","department":"Operations","priority":"normal","items":[{"product_id":1,"quantity":10,"unit_cost":50}]}'` | `201`, requisition-created envelope |
| `POST /admin/requisitions/:id/accept` | `curl -sS -X POST "$BASE/admin/requisitions/:requisition_id/accept" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, requisition-accepted envelope |
| `GET /admin/procurements` | `curl -sS "$BASE/admin/procurements?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, procurements envelope |
| `POST /admin/procurements/:id/receive` | `curl -sS -X POST "$BASE/admin/procurements/:procurement_id/receive" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"warehouse_location":"Main warehouse","items":[{"requisition_product_id":1,"quantity_received":10}]}'` | `200`, procurement-received envelope |
| `POST /admin/procurements/:id/on-hand` | `curl -sS -X POST "$BASE/admin/procurements/:procurement_id/on-hand" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"warehouse_location":"Main warehouse","payments":[{"company_account_id":1,"amount":500}]}'` | `200`, on-hand envelope |
| `GET /admin/procurement-payments/options` | `curl -sS "$BASE/admin/procurement-payments/options" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, payment options envelope |
| `GET /admin/procurement-payments` | `curl -sS "$BASE/admin/procurement-payments?perPage=10&page=1" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, procurement payments envelope |
| `POST /admin/procurement-payments` | `curl -sS -X POST "$BASE/admin/procurement-payments" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"procurement_id":1,"company_account_id":1,"amount":500,"payment_reference":"PO-001","paid_at":"2026-09-11"}'` | `201`, payment-created envelope |
| `GET /admin/procurement-payments/:id` | `curl -sS "$BASE/admin/procurement-payments/:payment_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, payment envelope |
| `PATCH /admin/procurement-payments/:id` | `curl -sS -X PATCH "$BASE/admin/procurement-payments/:payment_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"procurement_id":1,"company_account_id":1,"amount":500,"payment_reference":"PO-001","paid_at":"2026-09-11"}'` | `200`, payment-updated envelope |
| `DELETE /admin/procurement-payments/:id` | `curl -sS -X DELETE "$BASE/admin/procurement-payments/:payment_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, payment-deleted envelope |
| `GET /admin/stock-receipts` | `curl -sS "$BASE/admin/stock-receipts?perPage=10&page=1&procurement_id=1&product_id=1&warehouse_location=Main%20warehouse" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, stock receipts envelope |
| `POST /admin/stock-receipts` | `curl -sS -X POST "$BASE/admin/stock-receipts" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"procurement_id":1,"warehouse_location":"Main warehouse","items":[{"requisition_product_id":1,"quantity_received":10}]}'` | `201`, stock-saved envelope |
| `GET /admin/stock-receipts/:id` | `curl -sS "$BASE/admin/stock-receipts/:receipt_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, receipt detail envelope |
| `GET /admin/stocks` | `curl -sS "$BASE/admin/stocks?perPage=10&page=1" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, stock receipts envelope (alias) |
| `GET /admin/product-stocks/options` | `curl -sS "$BASE/admin/product-stocks/options" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, stock options envelope |
| `GET /admin/product-stocks` | `curl -sS "$BASE/admin/product-stocks?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, current stock envelope |
| `POST /admin/product-stocks` | `curl -sS -X POST "$BASE/admin/product-stocks" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"product_id":1,"warehouse_location":"Main warehouse","stock_quantity":25,"reason":"Initial stock"}'` | `201`, stock-saved envelope |
| `POST /admin/product-stocks/:id` | `curl -sS -X POST "$BASE/admin/product-stocks/:stock_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON" -d '{"stock_quantity":20,"reason":"Stock count"}'` | `200`, stock-adjusted envelope |
| `GET /admin/inventory-adjustments` | `curl -sS "$BASE/admin/inventory-adjustments?perPage=10&page=1&search=" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, adjustments envelope |
| `GET /admin/inventory-adjustments/:id` | `curl -sS "$BASE/admin/inventory-adjustments/:adjustment_id" -H "Authorization: Bearer $ADMIN_TOKEN" -H "$JSON"` | `200`, adjustment detail envelope |

Requisition optional fields are `department`, `priority` (`low`, `normal`, `high`, `urgent`), `required_by`, `supplier_name`, `reference_no`, and `notes`. Each receive/stock-receipt item requires a distinct `requisition_product_id` and `quantity_received >= 1`. On-hand confirmation requires one or more distinct company-account payments.

## Query parameters and upload notes

- List endpoints accept `page` and `perPage`; `perPage` is clamped to 1–100 where implemented. Many list endpoints also accept `search`.
- Dates are ISO-8601 dates (`YYYY-MM-DD`).
- IDs are numeric; the API rejects a non-numeric `{id}` route parameter.
- For products and users, use multipart form data when sending an image. Omit the image field for a JSON-only update.
- Never commit real access tokens, database credentials, or payment-gateway callback payloads to this repository.
