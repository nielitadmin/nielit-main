# Homepage AI assistant (index.php)

Floating chat on the public homepage. Answers questions about login, registration, fees, mock test, and centre contact.

## How it works (priority order)

1. **n8n chat** (recommended for staff updates): Master admin → **API & Automation** → enable **Homepage chat → n8n**, URL  
   `https://nielitbbsr.app.n8n.cloud/webhook/nielit-portal-chat` — import `docs/automation/n8n/nielit-portal-chat.json`.
2. **Staff activity (no n8n):** If you are **logged in as admin** on the same browser, ask **“student update report”** — reads the portal activity log directly.
3. **OpenAI (optional):** `config/portal_assistant.local.php` on the server:

```php
<?php
return [
    'openai_api_key' => 'sk-...',
    'openai_model' => 'gpt-4o-mini',
    'use_openai' => true,
];
```

## Files

| File | Role |
|------|------|
| `includes/portal_assistant_widget.php` | UI on `index.php` |
| `includes/portal_assistant_helper.php` | FAQ + OpenAI |
| `api/portal_assistant_chat.php` | POST JSON `{ message, history }` |
| `assets/css/portal-assistant.css` | Styles |
| `assets/js/portal-assistant.js` | Client |

Rate limit: 40 messages per hour per browser session.

## Security

- No admin or student private data in chat.
- Do not commit `portal_assistant.local.php` or API keys.