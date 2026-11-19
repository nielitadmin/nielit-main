# NIELIT student automation — n8n setup

The portal **pushes** events to n8n and exposes an **activity feed** for daily digests.  
Workflow JSON files in this folder are meant to be **imported in n8n.cloud** (you connect Gmail and API key there).

| Role | Email |
|------|--------|
| **n8n Gmail** (send alerts + email assistant inbox) | `nielitbbsr01@gmail.com` |
| **Alert / report To** (your inbox) | `saswatsomya111@gmail.com` (set in portal **Alert email**) |

### Activity logs → email (complete setup)

1. **Portal** → API & Automation → **Alert email** = `saswatsomya111@gmail.com`, webhook ON, secret saved.
2. **n8n (instant):** Import/replace with `nielit-student-updates-routing.json` — each log includes **log ID**, **IST time**, **details JSON** → Gmail uses `notify_email` from portal.
3. **n8n (daily digest):** Import `nielit-activity-daily-digest.json`, publish — **6 PM IST**, up to **500** log lines with update dates.
4. **Gmail send from:** `nielitbbsr01@gmail.com` (credential in n8n).

Connect **nielitbbsr01@gmail.com** on every Gmail and Gmail Trigger node in n8n. Student notifications are delivered **to** `saswatsomya111@gmail.com`. For “talk back”, email **to** `nielitbbsr01@gmail.com` **from** your personal address (or from the same account for testing).

## Can Cursor edit my n8n directly?

**No.** n8n.cloud is your account. The assistant cannot log in.

**What works instead:**

| What you do | What we do |
|-------------|------------|
| **Import** the JSON files below into n8n | Maintain those files in the repo; you pull/deploy or copy from the server |
| **Export** your current workflow (⋯ → Download) and add the file to this project | Edit the JSON and give you an updated import |
| Share **director email**, **your alert email**, and **webhook path** in chat | Tune the workflows (no password needed) |

**Do not** paste n8n passwords or Gmail OAuth secrets into chat or git.

Optional (advanced): n8n **Settings → API** creates an API key for their REST API — still usually easier to **import JSON** once.

---

## Files

| File | Purpose |
|------|---------|
| `nielit-student-updates-routing.json` | Instant webhook: secret check, **urgent** mail, **bulk** mail with director CC, normal updates |
| `nielit-activity-daily-digest.json` | **6:00 PM** poll of `activity_feed.php` → one summary email |
| `nielit-gmail-talk-back.json` | **Email in → reply out**: you email Gmail; n8n talks back (hello / help / REPORT) |
| `nielit-portal-chat.json` | **Homepage NIELIT Assistant** chat → n8n → activity feed / help text (sync JSON reply) |

Your existing webhook URL path was `nielit-student-updates`. The routing workflow uses the same path so you can **replace** the old workflow after export/backup, or change the Webhook node path and update the portal URL to match.

---

## Before import (portal)

1. **Master admin** → **API & Automation** (`/api/admin/manage_api_keys.php`).
2. Confirm **Enable instant webhook** ON, URL =  
   `https://nielitbbsr.app.n8n.cloud/webhook/nielit-student-updates`  
   Secret = `Nielitbbsr@2026` (or your chosen secret — must match n8n).
3. **Create API key** (Read Only is enough):
   - Name: `n8n daily digest`
   - Copy the key once (for the daily workflow HTTP node).

---

## Import routing workflow (urgent + bulk + normal)

1. In n8n, **backup**: open *NIELIT Student Update Notifications* → ⋯ → **Download**.
2. **Workflows** → **Import from file** → `nielit-student-updates-routing.json`.
3. Open the workflow and set **placeholders** (search nodes for `REPLACE_`):

   | Placeholder | Where |
   |-------------|--------|
   | `REPLACE_ADMIN_EMAIL` | All Gmail “To” fields |
   | `REPLACE_DIRECTOR_EMAIL` | Gmail “Bulk update” CC |
   | `Nielitbbsr@2026` | IF “Verify Shared Secret” (both sides) |

4. **Gmail** nodes: connect your existing Gmail credential (same as today).
5. **Activate / Publish** the workflow.
6. Disable or delete the **old** duplicate webhook workflow so only one listens on `nielit-student-updates`.

**Routing logic**

- **Urgent:** `student_inspector_purge`, `student_delete`, `student_inspector_delete` → subject `[URGENT] NIELIT student action`.
- **Bulk:** `student_excel_update`, `student_list_import` → normal subject + **CC director**.
- **Other** student actions → normal email to admin only.

---

## Import daily digest workflow

1. Import `nielit-activity-daily-digest.json`.
2. **HTTP Request** node:
   - URL: `https://nielitbhubaneswar.in/api/v1/activity_feed.php?since_id={{ $json.sinceId }}&limit=200`
   - Header: `X-API-Key` = your **n8n daily digest** key (paste in n8n, not in git).
3. Gmail **To** → `REPLACE_ADMIN_EMAIL`.
4. Schedule default: **18:00** server time (adjust time zone in the Schedule Trigger node).
5. Publish. First run starts from `since_id=0` only until static data is set; after first success it uses `next_since_id`.

---

## Test

1. **Routing:** In n8n, run Webhook test or edit student `NIELIT/2026/BBSR/0848` in the portal — expect one email in the right branch.
2. **Digest:** Execute workflow manually once; check email and that the second run does not repeat the same events.

---

## Email it and it talks back (`nielit-gmail-talk-back.json`)

**Idea:** You send a normal email **to** `nielitbbsr01@gmail.com` (from `saswatsomya111@gmail.com` or the same inbox for testing). Within about a minute, n8n replies in the same thread.

This does **not** change student records. It only **replies** with help text or a small activity report.

### Setup

1. Import `nielit-gmail-talk-back.json` as a **second** workflow (keep the webhook workflow as-is).
2. **Gmail Trigger** + **Gmail Reply** → credential **nielitbbsr01@gmail.com**.
3. **Only Owner** already allows `saswatsomya111@gmail.com` and `nielitbbsr01@gmail.com` only.
4. **Fetch Activity** → paste portal **Read API key** (`PASTE_PORTAL_API_KEY_HERE`) for REPORT replies.
5. **Publish** this workflow.

### How to use

| You send | It answers |
|----------|------------|
| `Hi` / `Hello` | Intro + what it can do |
| Body or subject contains **REPORT** | Last student events from activity feed |
| `webhook` / `connect` | Reminder + Executions link |
| `help` | Short capability list |

**Skip:** Replies are **not** sent for subjects like `NIELIT student record` / `URGENT` (your own alert emails).

### Real AI chat (optional later)

The JSON uses simple scripted replies. For ChatGPT-style conversation, add an **OpenAI** node in n8n between **Build Chat Reply** and **Gmail Reply** (n8n Assistant can help), or ask in Cursor for an `nielit-gmail-openai.json` once you have an OpenAI API key in n8n.

---

## Homepage chat → n8n (`nielit-portal-chat.json`)

1. Import `nielit-portal-chat.json`, set **Fetch Activity Feed** API key, secret = portal shared secret.
2. **Publish** workflow (path `nielit-portal-chat`, response mode **response node**).
3. Portal → **API & Automation** → enable **Homepage chat → n8n**, paste chat webhook URL, save.
4. On the homepage, open **NIELIT Assistant** → **Student updates** chip or type `student update report` (best when **admin is logged in**).

Works together with **student-updates** email webhook (push) and **activity feed** (pull).

---

## Change emails or actions later

Edit the workflow in n8n, or ask in Cursor to change the JSON in this folder and re-import.