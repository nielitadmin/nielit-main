# NIELIT student automation — n8n setup

The portal **pushes** events to n8n and exposes an **activity feed** for daily digests.  
Workflow JSON files in this folder are meant to be **imported in n8n.cloud** (you connect Gmail and API key there).

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

## Change emails or actions later

Edit the workflow in n8n, or ask in Cursor to change the JSON in this folder and re-import.