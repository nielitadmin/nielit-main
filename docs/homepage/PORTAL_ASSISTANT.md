# Saarthi — NIELIT Bhubaneswar AI Campus & Portal Assistant

Floating multilingual assistant rendered across all public pages (`index.php` and `public/*.php`).

## Features
- **Multilingual (English, Hindi / हिन्दी, Odia / ଓଡ଼ିଆ):** Auto-detects script/language and switches seamlessly via header language pills or phrases (`"Odia re kuha"`, `"Hindi mein batao"`, `"Switch to English"`).
- **Task A — Interactive 4-Step Portal Roadmap:** Explains Explore Courses → Check Batch & Seat Status → Register Online / via Saarthi → Download Admission Form PDF with clickable action buttons and `[UI_ACTION: SHOW_ROADMAP]` / `[UI_ACTION: SCROLL_COURSES]`.
- **Task B — Live Course & Location Search (`search_courses`):** Queries `courses`, `centres`, and `batches` in MySQL for live campuses, duration, delivery mode, fees, start dates, and available seats.
- **Task C — Conversational Student Registration (`register_student`):** Collects student details 1–2 fields per turn across 4 guided steps, displays a confirmation Summary Card, saves pending registration (`students`, `student_accounts`, `student_enrollments`, `education_details`), logs activity, dispatches confirmation email, and returns a secure **Download Admission PDF** button (`api/saarthi_admission_pdf.php`).
- **Task D — Safety & Helpdesk Escalation:** Refuses sensitive secrets (passwords, OTPs, UPI PINs, card numbers) and provides official helpdesk contacts (`0674-2960354`, `admin@nielitbhubaneswar.in`, `dir-bbsr@nielit.gov.in`) plus support callback logging (`support_tickets`).
- **Authenticated Portal Helpers:** Students logged into the same browser can inspect `"check my student details"`; logged-in staff can check emails, `NIELIT/...` Student IDs, and `"student update report"`.

## AI Backend Priority (`auto` mode)
1. **n8n chat webhook** (optional): Master Admin → **API & Automation** → enable **Homepage chat → n8n** (`docs/automation/n8n/nielit-portal-chat.json`).
2. **Google Gemini API** (recommended): Configure in Master Admin → **API & Automation** or `config/portal_assistant.local.php` (`gemini_api_key`, `gemini_model`) with live function calling (`search_courses`, `get_site_roadmap`, `register_student`, `log_support_callback`).
3. **OpenAI API** (optional): Configure in Master Admin → **API & Automation** or `config/portal_assistant.local.php` (`openai_api_key`, `openai_model`) with live tool calling.
4. **Smart Multilingual Fallback Engine:** Works with zero external API keys for EN/HI/OR roadmaps, live DB course searches, 4-step conversational registration, Admission PDF downloads, and helpdesk callback logging.

## Files
| File | Role |
|------|------|
| `includes/portal_assistant_widget.php` | Animated Saarthi UI, SVG mascot (`wave\|thinking\|guide\|happy\|celebrate`), language bar |
| `includes/portal_assistant_tools.php` | Live MySQL `search_courses`, `register_student`, `log_support_callback`, roadmap |
| `includes/portal_assistant_helper.php` | Multilingual orchestrator, Gemini/OpenAI tool-calling, EN/HI/OR fallback |
| `includes/portal_assistant_actions.php` | Authenticated student profile & admin lookup actions |
| `api/portal_assistant_chat.php` | POST JSON `{ action, message, lang, history }` returning Saarthi response + metadata |
| `api/saarthi_admission_pdf.php` | Token/session-authorized TCPDF Admission Application Form download |
| `assets/css/portal-assistant.css` | Saarthi widget, cards, animations, bottom-right dock, Night Mode support |
| `assets/js/portal-assistant.js` | Interactive cards renderer, animations, UI actions, cross-page `sessionStorage` sync |

## Security
- Never commit `config/portal_assistant.local.php` or API keys.
- Registration PDF download tokens are session-bound, record-scoped, and expire after 1 hour.
- Admin/student lookups enforce active PHP session checks (`portal_assistant_actions.php`).
