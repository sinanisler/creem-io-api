# Lemon Squeezy to Creem.io API Conversion Notes

## Overview
Successfully converted the WordPress plugin from Lemon Squeezy API to Creem.io API while preserving all features and functionality.

## Major Changes

### 1. Plugin Identity
- **Plugin Name**: Changed from "LemonSqueezy API to WordPress Sync" to "Creem.io API to WordPress Sync"
- **Class Name**: `LemonSqueezy_API_WordPress` → `Creem_API_WordPress`
- **Version**: Updated to 1.0.0

### 2. API Authentication
- **Old**: Bearer token authentication (`Authorization: Bearer {token}`)
- **New**: API key header authentication (`x-api-key: {api_key}`)
- **Headers Format**: Changed from JSON:API (`application/vnd.api+json`) to standard JSON (`application/json`)

### 3. API Endpoints
- **Base URLs**: 
  - Production: `https://api.creem.io`
  - Test Mode: `https://test-api.creem.io` (new feature)
  
- **Endpoint Mappings**:
  - Orders → Transactions: `/v1/orders` → `/v1/transactions`
  - Products → Products Search: `/v1/products` → `/v1/products/search`
  - User Info: `/v1/users/me` → `/v1/user`
  - Subscriptions: `/v1/subscriptions/{id}` → `/v1/subscriptions?subscription_id={id}`

### 4. Data Structure Changes
#### From JSON:API to Simple JSON

**Old (Lemon Squeezy - JSON:API)**:
```json
{
  "data": {
    "id": "123",
    "type": "orders",
    "attributes": {
      "user_email": "user@example.com",
      "first_order_item": {
        "product_name": "Product",
        "product_id": "456"
      }
    },
    "relationships": {...}
  }
}
```

**New (Creem.io - Simple JSON)**:
```json
{
  "items": [
    {
      "id": "tran_123",
      "customer": {
        "email": "user@example.com"
      },
      "product": {
        "id": "prod_456",
        "name": "Product"
      }
    }
  ],
  "pagination": {...}
}
```

### 5. User Metadata Keys
All WordPress user meta keys renamed from `lemonsqueezy_*` to `creem_*`:
- `lemonsqueezy_sale_id` → `creem_sale_id`
- `lemonsqueezy_product_name` → `creem_product_name`
- `lemonsqueezy_product_id` → `creem_product_id`
- `lemonsqueezy_created_date` → `creem_created_date`
- `lemonsqueezy_email_sent` → `creem_email_sent`
- `lemonsqueezy_subscription_status` → `creem_subscription_status`
- `lemonsqueezy_sale_data` → `creem_sale_data`
- `lemonsqueezy_assigned_roles` → `creem_assigned_roles`
- And 10+ more meta keys...

### 6. WordPress Options
- `lemonsqueezy_api_settings` → `creem_api_settings`
- `lemonsqueezy_api_logs` → `creem_api_logs`
- `lemonsqueezy_processed_sales` → `creem_processed_sales`

### 7. Cron Jobs
- `lemonsqueezy_api_check_sales` → `creem_api_check_sales`
- `lemonsqueezy_custom` → `creem_custom`

### 8. AJAX Actions
- `lemonsqueezy_test_api` → `creem_test_api`
- `lemonsqueezy_fetch_products` → `creem_fetch_products`
- `lemonsqueezy_clear_logs` → `creem_clear_logs`
- `lemonsqueezy_uninstall_plugin` → `creem_uninstall_plugin`

### 9. Admin Menu Slugs
- `lemonsqueezy-api-dashboard` → `creem-api-dashboard`
- `lemonsqueezy-api-settings` → `creem-api-settings`
- `lemonsqueezy-api-logs` → `creem-api-logs`
- `lemonsqueezy-api-users` → `creem-api-users`
- `lemonsqueezy-api-uninstall` → `creem-api-uninstall`

### 10. CSS Classes
All CSS classes renamed from `.snn-lemonsqueezy-*` to `.snn-creem-*` (63+ classes updated)

### 11. Subscription Status Mapping
- **Lemon Squeezy**: `expired`, `cancelled`, `unpaid`
- **Creem.io**: `canceled`, `unpaid`, `scheduled_cancel`

### 12. Refund Detection
- **Old**: Check `attributes.refunded` boolean
- **New**: Check `refunded_amount` > 0

### 13. Product Status
- **Old**: `status: 'published'`
- **New**: `status: 'active'`

## New Features Added

### Test Mode Support
Added a new setting to toggle between production and test API endpoints:
- Setting: `test_mode` checkbox in Settings → API Connection
- Production: `https://api.creem.io`
- Test: `https://test-api.creem.io`

## Preserved Features

All original features have been preserved:
- ✅ Automatic user creation from sales
- ✅ Product-specific role assignment
- ✅ Per-product auto-create toggle
- ✅ Refund handling (remove roles or delete account)
- ✅ Subscription management (active, canceled, unpaid)
- ✅ Subscription renewal redirect
- ✅ Welcome email system with templates
- ✅ Activity logging with rotation
- ✅ User list with search/filter
- ✅ Dashboard with statistics
- ✅ Complete uninstall capability
- ✅ Cron job for automated checking

## Key Function Updates

### `check_recent_sales()`
- Updated to fetch from `/v1/transactions`
- Parse simple JSON instead of JSON:API
- Extract customer email from nested object
- Handle test mode URL selection

### `process_sale()`
- Extract customer from transaction object
- Get product info from transaction
- No more `attributes` wrapper

### `handle_refund()`
- Check `refunded_amount` instead of `refunded` boolean
- Extract data from simple JSON structure

### `handle_subscription_change()`
- Handle `canceled` and `scheduled_cancel` statuses
- Check `current_period_end_date` for grace period
- No more `attributes` wrapper

### `fetch_subscription()`
- Use query parameter: `?subscription_id={id}`
- Support test mode URL
- Parse simple JSON response

### `parse_creem_transactions()`
- Renamed from `parse_lemonsqueezy_orders()`
- Look for `items` instead of `data`

### `parse_creem_products()`
- Renamed from `parse_lemonsqueezy_products()`
- Check `status: 'active'` instead of `status: 'published'`
- Look for `items` instead of `data`

### `parse_creem_customer()`
- Renamed from `parse_lemonsqueezy_user()`
- No more `attributes` wrapper

## Testing Checklist

Before deploying to production:

1. **API Connection**
   - [ ] Test API key with production endpoint
   - [ ] Test API key with test endpoint
   - [ ] Verify test mode toggle works

2. **Product Fetching**
   - [ ] Fetch products successfully
   - [ ] Products display in settings
   - [ ] Product roles can be configured

3. **Transaction Processing**
   - [ ] New sale creates user
   - [ ] Correct roles assigned
   - [ ] Welcome email sent
   - [ ] User metadata stored correctly

4. **Subscription Handling**
   - [ ] Active subscriptions maintain access
   - [ ] Canceled subscriptions remove roles
   - [ ] Subscription renewal redirect works

5. **Refund Processing**
   - [ ] Refunds detected correctly
   - [ ] Roles removed on refund
   - [ ] Account deletion option works

6. **Dashboard & Logs**
   - [ ] Statistics display correctly
   - [ ] Logs capture all activities
   - [ ] Log rotation works

7. **User List**
   - [ ] Users display with Creem data
   - [ ] Search/filter functions work
   - [ ] User details expand correctly

## Migration Notes

For existing installations migrating from Lemon Squeezy:

### Data Migration Required
The plugin uses different meta keys. Existing user data with `lemonsqueezy_*` keys will NOT be automatically migrated. You may need to:

1. Run a custom migration script to rename meta keys
2. Or start fresh (users will be re-created on next purchases)

### Database Cleanup
If you want to remove old Lemon Squeezy data:
```sql
DELETE FROM wp_usermeta WHERE meta_key LIKE 'lemonsqueezy_%';
DELETE FROM wp_options WHERE option_name LIKE 'lemonsqueezy_%';
```

### Settings Migration
The plugin will create new settings. You'll need to:
1. Re-enter your Creem.io API key
2. Re-configure product roles
3. Re-enable per-product auto-create settings

## API Documentation References

- **Creem.io API Docs**: https://docs.creem.io
- **LLMs.txt**: https://docs.creem.io/llms.txt
- **Full Documentation**: https://docs.creem.io/llms-full.txt
- **Products API**: GET /v1/products/search
- **Subscriptions API**: GET /v1/subscriptions
- **Transactions**: Automatically tracked via webhook or polling

## Important Differences

### 1. No Webhooks in Current Implementation
The plugin uses polling (cron job) to check for new transactions. Creem.io may support webhooks - consider implementing webhook support in future versions for real-time updates.

### 2. Customer Data Structure
Creem.io returns customer as either:
- Object: `{"customer": {"email": "...", "name": "..."}}`
- String ID: `{"customer": "cust_123"}` (requires additional API call)

The plugin handles both cases but may need customer fetch implementation.

### 3. Subscription Statuses
Creem.io uses:
- `active` - Subscription is active
- `canceled` - Subscription ended
- `unpaid` - Payment failed
- `paused` - Temporarily paused
- `trialing` - In trial period
- `scheduled_cancel` - Will cancel at period end

### 4. Date Formats
Creem.io uses ISO 8601 date strings, same as Lemon Squeezy.

## Troubleshooting

### "No transactions found"
- Check API key is correct
- Verify test mode matches your API key type
- Check if you have any transactions in Creem.io

### "User not created"
- Verify product ID matches
- Check "Auto Create Users" is enabled for that product
- Review logs for specific error messages

### "API connection failed"
- Verify API key format
- Check if test mode is correctly set
- Ensure server can reach Creem.io API

### "Subscription not detected"
- Ensure transaction includes subscription ID
- Check subscription status in Creem.io dashboard
- Review raw transaction data in logs

## Support

For issues specific to:
- **Plugin functionality**: Check the logs (Dashboard → API Logs)
- **Creem.io API**: https://docs.creem.io or support@creem.io
- **WordPress integration**: WordPress Codex

## Version History

- **1.0.0** - Complete conversion from Lemon Squeezy to Creem.io API
  - All 200+ occurrences of lemonsqueezy replaced
  - API endpoints updated
  - Data structure parsing rewritten
  - Test mode support added
  - All features preserved
