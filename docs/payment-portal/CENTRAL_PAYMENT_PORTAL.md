# Central payment portal for NIELIT Bhubaneswar

**Site:** `https://payment.nielitbhubaneswar.in`  
**Gateway:** Razorpay, used only by this portal  
**Purpose:** One place for every online payment and **money receipt** across the MIS, mock test site, and any later NIELIT Bhubaneswar website.

This is possible. It is a payment hub. Each website asks the hub to collect money. The hub opens Razorpay, stores the result, issues one **receipt number** series, and sends the person back to the website that started the payment.

The current MIS already collects some course fees itself. `includes/payment_gateway_helper.php` creates Razorpay orders and stores them in `online_course_payments`. The student page `student/fees.php` opens Razorpay Checkout directly. That direct path is what this portal replaces. After the hub is live, MIS and the other sites stop holding Razorpay keys.

---

## 1. What the portal is responsible for

The payment site owns all of the following:

- Razorpay Key ID and Key Secret. No other website stores these.
- Creating the Razorpay order.
- Opening the Razorpay checkout page.
- Checking the Razorpay signature when the payer returns.
- Receiving the Razorpay webhook, which is the final proof that money was captured.
- One **money receipt** number series for every website (stored as `invoice_number` in the database for compatibility).
- A record of which website started the payment, who paid, what it was for, the amount, the status, and the Razorpay ids.
- The receipt the payer and the office can open later.
- A register the accounts office can filter by website, centre, date, course, and status.

Each other website owns its own business rule: who may pay, the fee amount, and what “paid” unlocks (course access, mock test, hostel fee, and so on). The payment site does not decide those rules. It only collects and records the money.

---

## 2. Websites that connect to it

| Source code | Website | Examples of payments |
| --- | --- | --- |
| `mis` | nielitbhubaneswar.in (this MIS) | Course fee, workshop fee |
| `mock` | mock test site | Mock test fee, re-attempt fee |
| later codes | any new NIELIT Bhubaneswar site | Hostel, recruitment, event |

Each source is registered once on the payment portal:

- source code, for example `mis`
- display name
- allowed return URL prefixes, so a payment can only send the payer back to that site
- a shared secret used to sign the payment request
- active or inactive

A website that is not registered cannot start a payment.

---

## 3. How a payment moves

```text
Payer on MIS or mock test
        |
        | 1. That site checks the fee and creates a local “payment requested” record
        v
payment.nielitbhubaneswar.in/pay/{token}
        |
        | 2. Portal checks the signature, amount, and source
        | 3. Portal creates the Razorpay order
        v
Razorpay checkout (card, UPI, netbanking)
        |
        | 4. Payer finishes or cancels
        v
Portal verifies the signature and waits for the webhook
        |
        | 5. Portal issues the money receipt and marks the payment paid
        | 6. Portal tells the source website
        v
Payer returns to MIS or mock test, which unlocks the service
```

The payer should see the NIELIT payment page before Razorpay, with the purpose, amount, and which office the payment is for. Razorpay is the last step, not the first.

### Step by step

1. The student clicks **Pay** on MIS or the mock test site.
2. That site calculates the amount on the server. The browser must not be trusted for the amount.
3. That site creates its own reference, for example `MIS-FEE-18422`, and calls the portal:

   `POST https://payment.nielitbhubaneswar.in/api/v1/checkout`

4. The portal stores a checkout and returns a one-time link:

   `https://payment.nielitbhubaneswar.in/pay/8f3c...`

5. The source site redirects the browser to that link.
6. The portal creates the Razorpay order and opens checkout. The Razorpay order notes store the portal payment id, source code, and source reference. Those notes are how a later search answers “this Razorpay payment came from MIS, for this student, for this course.”
7. After Razorpay, the portal verifies `order_id|payment_id` with the Key Secret. A matching signature means Razorpay sent that response. It is not yet the accounts record.
8. The Razorpay webhook `payment.captured` is the record that money was captured. The portal marks the payment **paid** only after that event, or after a server-side fetch of the payment from Razorpay if the webhook is delayed.
9. The portal assigns the receipt number, stores the record, and notifies the source site.
10. The browser returns to the source site’s success URL. That site asks the portal for the status, then unlocks the course or mock test. It does not trust the browser alone.

If the payer closes the window after paying, the webhook still marks the payment paid and notifies the source site. The student can open the receipt from the payment portal later.

---

## 4. What each website sends

The checkout request is a signed JSON body. Amount is in paise so `500.00` rupees is `50000`.

```json
{
  "source": "mis",
  "reference": "MIS-FEE-18422",
  "amount": 50000,
  "currency": "INR",
  "purpose": "Course fee",
  "description": "Bootcamp on RTL, IP Integration and SOC Signoff",
  "payer": {
    "name": "Riyan Nayak",
    "email": "riyan@example.com",
    "phone": "9876543210",
    "external_id": "NIELIT/2026/BRIIS/0001"
  },
  "context": {
    "centre": "Bhubaneswar",
    "course_id": 12,
    "scheme": "Regular-Non Project"
  },
  "return_url": "https://nielitbhubaneswar.in/student/payment_return.php",
  "notify_url": "https://nielitbhubaneswar.in/api/payment_hub_notify.php"
}
```

The source site signs the raw body with HMAC-SHA256 and its shared secret, and sends that signature in the header `X-Payment-Signature`. The portal rejects a request when the signature does not match, the return URL is not on the allowed list, the amount is not a positive number, or the same `source + reference` is already paid.

`context` is stored and shown on the invoice. It is how the office sees which centre, course, or test the money was for. The portal does not interpret it.

---

## 5. Records kept on the payment portal

### Checkout

Created when a website asks for a payment link. Status values:

| Status | Meaning |
| --- | --- |
| `created` | Link issued, Razorpay not opened yet |
| `attempted` | Razorpay order created |
| `paid` | Money captured and invoice issued |
| `failed` | Razorpay reported a failure |
| `cancelled` | Payer closed checkout |
| `expired` | Link older than the allowed time, for example 30 minutes |

### Payment

One row per successful or failed attempt.

| Field | Example |
| --- | --- |
| source | `mis` |
| source reference | `MIS-FEE-18422` |
| payer name, email, phone, external id | student or applicant |
| purpose and description | course fee, mock test |
| context | centre, course, scheme |
| amount and currency | `500.00 INR` |
| Razorpay order id | `order_...` |
| Razorpay payment id | `pay_...` |
| method | UPI, card, netbanking |
| status | `paid` |
| receipt number (`invoice_number` in API) | `NIELIT-BBSR-2026-27-000184` |
| paid at | date and time |

### Money receipt (not a tax invoice)

Issued only when status becomes `paid`. One number is never reused.

Suggested series:

`NIELIT-BBSR-{financial year}-{6 digit sequence}`

Example: `NIELIT-BBSR-2026-27-000184`

The financial year follows the office year already used on Report Monitor, April to March. April 2026 through March 2027 is `2026-27`.

The printable money receipt shows:

- NIELIT Bhubaneswar name, address, and GSTIN if supplied on the letterhead
- receipt number and date
- received from (payer name and id)
- amount in figures and words, purpose, mode of payment (online)
- TRN/UTR (Razorpay payment id), bank line, institute vs platform charge breakdown
- authorised signatory line and footer note

A credit note, if a refund is issued, uses a separate series `NIELIT-BBSR-CN-2026-27-000012` and points at the original receipt number. The original receipt is not deleted.

### Webhook log

Every Razorpay webhook is stored with its event id. The same event id is ignored the second time, so a repeated webhook cannot create a second receipt.

---

## 6. How the office uses the portal

Staff sign in on `payment.nielitbhubaneswar.in`. Suggested screens:

1. **Today.** Count and sum of paid, failed, and pending payments, split by website.
2. **Payments.** Filter by date, website, centre, status, payer name, student id, Razorpay payment id, or receipt number.
3. **Money receipt.** Open or print the PDF. The same signed link can be sent to the payer (`/pay/receipt.php` or `/pay/invoice.php`).
4. **Sources.** Add one entry per integrating website (code, display name, allowed return URL prefixes, shared secret). Edit, deactivate, or rotate secrets without touching Razorpay keys. MIS and mock are seeded on first install only.
5. **Gateway.** The **only** Razorpay configuration: Key ID, Key Secret, webhook secret, and test or live mode, saved in the hub database via this screen (not in `secrets.php` or MIS). After changes, use **Test Razorpay connection**.
6. **Export.** CSV of the filtered register for Tally or the accounts workbook. Columns include receipt number, date, source, reference, payer, purpose, centre, amount, method, Razorpay payment id, status.

This register answers the two questions in the request:

- **Where was the transaction made?** The source website and the return URL host.
- **To whom?** The payer name, email, phone, and the external id from that website (student id, applicant id, or test user id).

---

## 7. What MIS changes

Today MIS calls Razorpay from `student/fees.php` and `includes/payment_gateway_helper.php`.

After cutover:

1. `student/fees.php` no longer loads `checkout.razorpay.com`. The Pay button asks MIS to create a hub checkout and then redirects to `payment.nielitbhubaneswar.in/pay/...`.
2. Razorpay keys are removed from the MIS gateway screen, or that screen is limited to “payments are collected by the central portal.”
3. MIS keeps `online_course_payments` as its local copy, with two new columns: `hub_payment_id` and `hub_invoice_number`.
4. A new endpoint, for example `api/payment_hub_notify.php`, accepts the portal’s paid notification, checks its signature, and marks the local fee paid.
5. `student/payment_return.php` reads the hub payment id from the return URL, asks the portal for status, and shows the invoice link.
6. Existing paid rows stay in MIS. They are not renumbered. New invoices use the central series. A one-time import can copy old paid rows into the portal so the office has one search screen, marked `source = mis` and `legacy = yes`.

The mock test site follows the same six steps with `source = mock`.

---

## 8. Security rules

- Razorpay Key Secret and webhook secret exist only on the payment server.
- Each source secret exists only on that website and on the payment server.
- Amount, payer, and purpose are taken from the signed server request, then stored. The pay page does not accept a new amount from the browser.
- Return URLs must start with a prefix registered for that source.
- Checkout links expire.
- The paid notification to MIS or mock test is signed the same way. The source site marks a fee paid only when that signature matches and the portal status is `paid`.
- Razorpay webhooks are verified with the webhook secret before any invoice is issued.
- The portal is HTTPS only. `payment.nielitbhubaneswar.in` is a DNS record pointing at this application, not a folder on the public MIS site.
- Admin access is separate from the MIS admin login, with its own users, or SSO later. Payment staff and MIS staff do not have to be the same accounts.

---

## 9. Razorpay setup

Use one Razorpay account for NIELIT Bhubaneswar unless the bank requires a different merchant for a centre. One account keeps one settlement and one webhook.

In the Razorpay dashboard:

- Create live API keys and store them only in the portal.
- Set the webhook URL to `https://payment.nielitbhubaneswar.in/api/v1/razorpay/webhook`.
- Subscribe to `payment.captured`, `payment.failed`, and `refund.processed`.
- Put the portal payment id, source code, and source reference in the order notes when the order is created.

Settlements, refunds initiated in the Razorpay dashboard, and the bank statement remain in Razorpay. The portal mirrors them. It does not replace the Razorpay settlement report.

Refunds should be started from the portal so the credit note and the source website stay in step. The portal calls Razorpay’s refund API, then on `refund.processed` it writes the credit note and notifies the source site.

---

## 10. Suggested build order

### Phase 1. Portal foundation

**Code location (this repo):** `payment-portal/` — deploy as document root for `payment.nielitbhubaneswar.in`.

**Implemented in portal code:** checkout + pay flow, webhooks (`payment.captured`, `payment.failed`, `refund.processed`), refunds with credit notes, admin outcomes guide, signed notify events for paid / failed / cancelled / refunded / partial_refund, **dynamic Sources**, **Payments filters + CSV export**, **dashboard today-by-website**, **legacy CSV import**, **Invoice & GST settings** + designer, API aliases `/api/v1/checkout` and `/api/v1/payment_status` (Apache rewrite).

- Host `payment.nielitbhubaneswar.in`.
- Tables for sources, checkouts, payments, invoices, and webhook events.
- Signed `POST /api/v1/checkout` (also `checkout.php`).
- Pay page and Razorpay checkout.
- Webhook and invoice PDF.
- Admin list and invoice view.
- Test mode with Razorpay test keys.

### Phase 2. MIS

**Implemented in MIS code (enable via `config/payment_hub.php` from `config/payment_hub.example.php`):** hub checkout redirect from course fees, signed `api/payment_hub_notify.php`, return handling via `student/payment_return.php` + portal status API, `online_course_payments.hub_payment_id` / `hub_invoice_number`. Direct Razorpay on fees is skipped when the hub config is enabled.

- Replace the direct Razorpay button on course fees.
- Notify endpoint and return page.
- One real course fee paid in test mode, then one in live mode.
- Confirm the MIS student record shows paid only after the portal says paid.

### Phase 3. Mock test and other sites

- Register the mock test source.
- Same checkout and notify pattern.
- The office can filter “MIS” and “Mock test” in one register.

### Phase 4. Accounts

- CSV export. **Done** (Admin → Payments → Export CSV).
- GST fields on the invoice if required. **Done** (Admin → Invoice & GST + designer).
- Legacy import of MIS payments already stored in `online_course_payments`. **Done** (Admin → Legacy import CSV).
- Refund and credit note. **Done** (portal refunds + webhook `refund.processed`).

Do not turn off the current MIS Razorpay button until Phase 2 has passed a live payment.

---

## 11. Hosting

| Item | Choice |
| --- | --- |
| Name | `payment.nielitbhubaneswar.in` |
| App | Its own folder and database, not tables mixed into everyday MIS edits |
| PHP | Same major version as the MIS server |
| Database | MySQL, database name such as `nielit_payments` |
| Secrets | Outside the web root, or in environment variables |
| Backups | Daily database backup, kept with the invoice PDFs |

The MIS server must be able to reach `https://payment.nielitbhubaneswar.in` on port 443. The payment server must be able to reach the MIS and mock-test notify URLs.

---

## 12. What this design does not do

- It does not put Razorpay keys on MIS, the mock test site, or a new website.
- It does not let a website mark itself paid without the portal confirming capture.
- It does not merge the student database, mock-test users, and payment staff into one login. Only the payment event is shared.
- It does not change the course fee rules already stored on each course in MIS. MIS still decides the amount, then asks the portal to collect that amount.

---

## 13. First decision before building

Confirm these with the accounts office and then Phase 1 can start:

1. One Razorpay merchant for all NIELIT Bhubaneswar websites, or a separate merchant per centre.
2. The invoice prefix and whether GST must be printed.
3. Who may open the payment admin: accounts section, MIS admin, or both.
4. Which payment goes first after MIS course fees: mock test, or another existing site.
