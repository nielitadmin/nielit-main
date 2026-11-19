# Homepage AI assistant (index.php)

Floating chat on the public homepage. Answers questions about login, registration, fees, mock test, and centre contact.

## How it works

- **Local mode (default):** Keyword/FAQ replies — no API key required.
- **OpenAI mode (optional):** Create `config/portal_assistant.local.php` on the server (not in git):

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