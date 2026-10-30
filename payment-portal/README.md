# NIELIT Bhubaneswar — Central Payment Portal

**Production URL:** `https://payment.nielitbhubaneswar.in`

Separate app from MIS. Own database (`nielit_payments`). Razorpay keys live only here.

## Local setup (XAMPP)

1. Create database:
   ```sql
   CREATE DATABASE nielit_payments CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Copy `config/database.example.php` → `config/database.php` and adjust credentials.
3. Copy `config/secrets.example.php` → `config/secrets.php` and set a strong **admin password** (first login only).
4. Open the site once; tables are created automatically.
5. Configure Razorpay under **Admin → Gateway** (single source for all API keys).
6. Open `http://localhost/public_html/payment-portal/` — schema runs on first request.
7. Admin: `http://localhost/public_html/payment-portal/admin/login.php`

## Production

See **`PRODUCTION.md`** for Hostinger/cPanel steps.

1. Point subdomain **`payment.nielitbhubaneswar.in`** document root to this **`payment-portal/`** folder.
2. Create MySQL DB + user (e.g. `u664913565_payment`) in cPanel.
3. Add **`config/database.php`** and **`config/secrets.php`** on the server (never commit them).
4. Visit https://payment.nielitbhubaneswar.in/ once to create tables, then **Admin → Gateway** for Razorpay keys.

`config/app.php` detects the production hostname and sets `HUB_APP_URL` to `https://payment.nielitbhubaneswar.in` automatically.

## API (Phase 1)

- `POST /api/v1/checkout.php` — signed checkout (header `X-Payment-Signature`)
- `GET /api/v1/payment_status.php` — status by token or reference (signed)
- `POST /api/v1/razorpay/webhook.php` — Razorpay webhooks
- `GET /pay/?token=…` — payer page + Razorpay Checkout

### Refunds and outcomes

- Admin **Payments → View / refund** — full or partial refund via Razorpay; credit note series `NIELIT-BBSR-CN-…`.
- **Admin → Outcomes** — table of every checkout and payment status.
- Webhooks: `payment.captured`, `payment.failed`, `refund.processed`.
- Source notify events: `paid`, `failed`, `cancelled`, `refunded`, `partial_refund`.
