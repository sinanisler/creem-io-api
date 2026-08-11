# Creem.io WordPress Plugin 

<a href="https://github.com/sponsors/sinanisler">
<img src="https://img.shields.io/badge/Consider_Supporting_My_Projects_❤-GitHub-d46" width="300" height="auto" />
</a>
<br><br>

Automatically create WordPress users from Creem.io sales with API.


## Features

- Automatic user creation from purchases
- Product-specific role assignment
- Per-product auto-create toggle
- Refund handling
- Subscription management
- Welcome emails
- Activity logging
- User management UI
- Dashboard statistics



## Installation

1. Download the [latest release plugin ZIP](https://github.com/sinanisler/creem-io-api/releases) from GitHub and upload via **Plugins → Add New → Upload**
2. Activate the plugin
3. Go to **Creem.io API → Settings** 
4. Paste your API key (that you copied from Creem.io dashboard → Settings → API Keys)
5. Click **Test & Fetch Products** to load your products
6. Enable **Auto Create Users** for each product you want to trigger account creation
7. Save Settings — the cron job starts automatically

**Shortcode:** Use `[creem_billing_link]` anywhere to show a billing portal link for logged-in users.
Optional attributes: `text`, `class`, `not_logged_in_text`, `no_subscription_text`
Example: `[creem_billing_link text="Manage Billing" class="button"]`
Example: `[creem_billing_link not_logged_in_text="Please log in to manage your subscription."]`


## Developer Hooks

- **`creem_skip_renewal_redirect`** — return `true` to keep specific pages reachable for users whose subscription has expired (otherwise they are redirected to the configured renewal page). Defaults to `false`. Useful for whitelisting cart/checkout/contact pages.

  ```php
  add_filter('creem_skip_renewal_redirect', function ($skip) {
      return is_page(array('cart', 'checkout', 'contact'));
  });
  ```


## Known Limitations

- **One active subscription per customer.** The plugin is designed around a single active subscription per email address. If a customer holds 2+ concurrent active subscriptions, a warning is logged (`Multiple active subscriptions for customer`) and the latest subscription is tracked; the older subscription's cancellation/expiry may not be fully reconciled. Review the logs manually if you offer multiple concurrent subscriptions.
- **Paused subscriptions are downgraded**, not revoked: a paused subscriber is moved to the configured default role(s) and restored to their product role(s) when the subscription resumes. Paused users are never redirected to the renewal page (they did not cancel).


## API reference (consumed endpoints)

This plugin polls the Creem **REST API** only (no webhooks). Every request uses the `x-api-key` header and targets `https://api.creem.io` (or `https://test-api.creem.io` in test mode). Below are the response shapes the plugin relies on — fields trimmed and values redacted (no real customer data).

### `GET /v1/transactions/search?page_number=&page_size=`
The cron entry point. A transaction has **no `product`** field and `customer` is a **string ID**, so the plugin fetches the subscription (below) to resolve the product and the customer email.
```json
{
  "items": [
    {
      "id": "tran_xxx",
      "object": "transaction",
      "type": "invoice",            // "invoice" = subscription | "payment" = one-time
      "status": "paid",
      "amount": 2900, "amount_paid": 2318, "currency": "EUR",
      "refunded_amount": null,      // null when no refund; otherwise cents
      "subscription": "sub_xxx",    // subscription id (string) on invoices
      "customer": "cust_xxx",       // string id, NOT an object
      "order": "ord_xxx",
      "period_start": 1781374738000, "period_end": 1783966738000,
      "created_at": 1781374745506,
      "mode": "test"
    }
  ],
  "pagination": { "total_records": 2, "total_pages": 1, "current_page": 1, "next_page": null, "prev_page": null }
}
```

### `GET /v1/subscriptions?subscription_id=`
The source of product + customer email + status + period end.
```json
{
  "id": "sub_xxx",
  "object": "subscription",
  "status": "canceled",             // active | trialing | paused | canceled | unpaid
  "product": { "id": "prod_xxx", "name": "Premium", "object": "product", "status": "active", "mode": "test" },
  "customer": { "id": "cust_xxx", "email": "user@example.com", "name": "Jane Doe", "country": "IT", "object": "customer" },
  "collection_method": "charge_automatically",
  "current_period_start_date": "2026-06-13T18:18:58.000Z",
  "current_period_end_date": "2026-07-13T18:18:58.000Z",
  "canceled_at": "2026-07-13T06:30:00.700Z",   // null unless canceled
  "mode": "test"
}
```

### `GET /v1/products/search?page_number=&page_size=`
Used by the "Test & Fetch Products" action.
```json
{
  "items": [
    { "id": "prod_xxx", "object": "product", "name": "Pro", "status": "active",
      "price": 4900, "currency": "EUR", "billing_type": "recurring", "billing_period": "every-month",
      "tax_mode": "exclusive", "tax_category": "saas", "mode": "test" }
  ],
  "pagination": { "total_records": 5, "total_pages": 3, "current_page": 1, "next_page": 2, "prev_page": null }
}
```

### `POST /v1/customers/billing`
Body: `{ "customer_id": "cust_xxx" }`. Returns a billing-portal URL.
```json
{ "customer_portal_link": "https://creem.io/my-orders/login/xxxx" }
```

### Subscription status handling
Documented `status` values: `active`, `trialing`, `paused`, `canceled`, `unpaid`.
- `active` / `trialing` → product roles granted.
- `paused` → downgraded to the configured default role(s); restored on resume.
- `canceled` / `unpaid` → access revoked **after** `current_period_end_date` has passed.

> The REST subscription endpoint returns ended subscriptions as `canceled`. Statuses sometimes mentioned elsewhere (`expired`, `scheduled_cancel`, `past_due`) are not produced by this endpoint.


<img width="1903" height="976" alt="image" src="https://github.com/user-attachments/assets/ae03fa9c-aafe-427b-b3fb-6ae898111fc0" />
<img width="1893" height="975" alt="image" src="https://github.com/user-attachments/assets/ba8371c2-2070-4708-8820-e78fcb7993aa" />
<img width="1917" height="1080" alt="image" src="https://github.com/user-attachments/assets/e02b5538-e78c-46d5-af6f-a0a355473ead" />
<img width="1919" height="1080" alt="image" src="https://github.com/user-attachments/assets/b6987dc3-8c20-472f-9c36-1963d138c8b4" />

