# NIELIT Bhubaneswar — Central Payment Portal

**Production URL:** `https://payment.nielitbhubaneswar.in`

Separate app from MIS. Own database (`nielit_payments`). Razorpay keys live only here.

## Local setup (XAMPP)

1. Create database:
   ```sql
   CREATE DATABASE nielit_payments CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Copy `config/database.example.php` → `config/database.php` and adjust credentials.
3. Copy `config/secrets.example.php` → `config/secrets.php` and set admin password + Razorpay test keys.
4. Open `http://localhost/public_html/payment-portal/` — schema runs on first request.
5. Admin: `http://localhost/public_html/payment-portal/admin/login.php`

## Production

Point the subdomain document root to this `payment-portal/` folder. Set `HUB_APP_URL` in `config/app.php` to `https://payment.nielitbhubaneswar.in`.

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
