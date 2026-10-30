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
   - Strong `admin_password`
   - Razorpay **live** or **test** keys
   - Webhook secret from Razorpay dashboard

5. **Deploy code** — git pull or upload the `payment-portal/` tree.  
   Do **not** overwrite `config/database.php` or `config/secrets.php` on the server when updating.

6. Open once in the browser (creates tables automatically):
   - https://payment.nielitbhubaneswar.in/
   - https://payment.nielitbhubaneswar.in/admin/login.php

7. **Razorpay webhook** (Dashboard → Webhooks):
   - URL: `https://payment.nielitbhubaneswar.in/api/v1/razorpay/webhook.php`
   - Events: `payment.captured`, `payment.failed`, `refund.processed`

8. **Admin → Sources** — copy the **MIS shared secret** into MIS when you integrate Phase 2.

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

## 4. Security

- `config/database.php` and `config/secrets.php` are **gitignored** — keep them only on the server.
- Use **HTTPS** only in production (Hostinger usually provides SSL for the subdomain).
