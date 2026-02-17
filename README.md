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


<img width="1903" height="976" alt="image" src="https://github.com/user-attachments/assets/ae03fa9c-aafe-427b-b3fb-6ae898111fc0" />
<img width="1893" height="975" alt="image" src="https://github.com/user-attachments/assets/ba8371c2-2070-4708-8820-e78fcb7993aa" />
<img width="1917" height="1080" alt="image" src="https://github.com/user-attachments/assets/e02b5538-e78c-46d5-af6f-a0a355473ead" />
<img width="1919" height="1080" alt="image" src="https://github.com/user-attachments/assets/b6987dc3-8c20-472f-9c36-1963d138c8b4" />

