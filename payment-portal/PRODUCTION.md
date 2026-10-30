# Production: payment.nielitbhubaneswar.in

## 1. Hostinger / cPanel

1. **Subdomain** `payment.nielitbhubaneswar.in` → document root should be the **`payment-portal`** folder  
   (the folder that contains `index.php`, `admin/`, `api/`).

2. **MySQL** — database name and user (e.g. `u664913565_payment`) must exist in cPanel.  
   Grant the user **ALL** privileges on that database.

3. On the server, create **`config/database.php`** (not in git):

   ```php
   define('HUB_DB_HOST', 'localhost');
   define('HUB_DB_USER', 'u664913565_payment');
   define('HUB_DB_PASS', '…');
   define('HUB_DB_NAME', 'u664913565_payment');
   ```

   Copy from `config/database.example.php` and use your real password.

4. Create **`config/secrets.php`** from `config/secrets.example.php`:
   - Strong `admin_password` for the first staff login (seeded once into `hub_admin_users`).

5. **Deploy code** — git pull or upload the `payment-portal/` tree.  
   Do **not** overwrite `config/database.php` or `config/secrets.php` on the server when updating.

6. Open once in the browser (creates tables automatically):
   - https://payment.nielitbhubaneswar.in/
   - https://payment.nielitbhubaneswar.in/admin/login.php

7. **Admin → Gateway** — enter Razorpay **Key ID**, **Key secret**, **webhook secret**, and test/live mode.  
   This is the **only** place Razorpay credentials are stored. Run **Test Razorpay connection** after saving.

8. **Razorpay webhook** (Dashboard → Webhooks):
   - URL: `https://payment.nielitbhubaneswar.in/api/v1/razorpay/webhook.php`
   - Events: `payment.captured`, `payment.failed`, `refund.processed`

9. **Admin → Sources** — copy the **MIS shared secret** into MIS when you integrate Phase 2.

## 2. How URLs are chosen

`config/app.php` sets:

- **Production host** `payment.nielitbhubaneswar.in` → `HUB_APP_URL = https://payment.nielitbhubaneswar.in`
- **Local XAMPP** → `http://localhost/public_html/payment-portal`

Override with environment variables `HUB_APP_URL` or `HUB_ENV` if your host supports them.

## 3. Quick checks

| Check | URL |
|--------|-----|
| Home | https://payment.nielitbhubaneswar.in/ |
| Admin login | https://payment.nielitbhubaneswar.in/admin/login.php |
| DB error | Fix `config/database.php` or create DB in cPanel |
| Razorpay | Admin → Gateway |

**HTTP 500 after editing `database.php`:** Line 1 must be exactly `<?php` (not `k?php` or missing `?`). Save as UTF-8 without BOM.

## 4. Security

- `config/database.php` and `config/secrets.php` are **gitignored** — keep them only on the server.
- Use **HTTPS** only in production (Hostinger usually provides SSL for the subdomain).

## 5. Admin dashboard (layout & setup)

| Requirement | Detail |
|-------------|--------|
| **Layout** | Fixed left sidebar (268px); main content and footer sit to the right—no overlap with sidebar or quick-link cards. |
| **First visit** | Blue **Complete setup** banner until Razorpay keys + webhook secret are saved. |
| **Gateway** | Admin → **Gateway** only — Razorpay Key ID, Key secret, webhook secret, test/live mode (database; not `secrets.php`). |
| **Sources** | Admin → **Sources** — add a source per website (code, return URLs, secret); unlimited sites |
| **Payments** | Filters (date, source, centre, status, search) + **Export CSV** for accounts |
| **Legacy import** | Admin → **Legacy import** — CSV of old MIS paid rows into the hub register |
| **Invoice & GST** | Admin → **Invoice & GST** — org address, GSTIN, show GST line; **Invoice designer** for layout |
| **Staff login** | Default bootstrap user from `secrets.php` (first DB seed only); change password after login. |
| **Assets** | Deploy `assets/css/hub-theme.css` after UI updates; hard-refresh (Ctrl+F5). |
