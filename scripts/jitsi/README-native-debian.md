# Jitsi Meet — Native Debian/Ubuntu (systemd + Prosody)

Use this guide if your server was installed with **apt packages** (not Docker):

- Services: `prosody`, `jicofo`, `jitsi-videobridge2`, `nginx`
- Config: `/etc/prosody/conf.avail/`, `/etc/jitsi/meet/`
- **No** `/opt/jitsi-meet/.env` file

This matches NIELIT Online Classes JWT from the portal (`Admin → Online Classes → Video Server Controls`).

---

## 1. Find your domain config

Your main Prosody host file is usually:

```
/etc/prosody/conf.avail/meet.nielitbhubaneswar.in.cfg.lua
```

(or symlinked from `/etc/prosody/conf.d/`)

Web config:

```
/etc/jitsi/meet/meet.nielitbhubaneswar.in-config.js
/etc/jitsi/meet/meet.nielitbhubaneswar.in-interface_config.js
```

Custom overrides (recommended):

```
/usr/share/jitsi-meet-cfg/web/custom-config.js
/usr/share/jitsi-meet-cfg/web/custom-interface_config.js
```

Copy from this repo:

```bash
sudo cp custom-config.js /usr/share/jitsi-meet-cfg/web/custom-config.js
sudo cp custom-interface_config.js /usr/share/jitsi-meet-cfg/web/custom-interface_config.js
```

---

## 2. Enable JWT on Prosody (fixes "Authentication failed")

Edit the VirtualHost block for your meet domain in  
`/etc/prosody/conf.avail/meet.nielitbhubaneswar.in.cfg.lua`

**Replace** `authentication = "internal_hashed"` (or `jitsi-anonymous`) with **token** auth:

```lua
VirtualHost "meet.nielitbhubaneswar.in"
    authentication = "token"
    app_id = "nielit_portal"
    app_secret = "YOUR_LONG_RANDOM_SECRET"
    allow_empty_token = false
    enable_domain_verification = false

    ssl = {
        key = "/etc/prosody/certs/meet.nielitbhubaneswar.in.key";
        certificate = "/etc/prosody/certs/meet.nielitbhubaneswar.in.crt";
    }

    modules_enabled = {
        "bosh";
        "websocket";
        "smacks";
        "ping";
        "speakerstats";
        "conference_duration";
        "room_metadata";
        "muc_lobby_rooms";
        "muc_breakout_rooms";
        "av_moderation";
        "token_verification";
        "token_moderation";
    }

    c2s_require_encryption = false
    lobby_muc = "lobby.meet.nielitbhubaneswar.in"
    breakout_rooms_muc = "breakout.meet.nielitbhubaneswar.in"
    main_muc = "conference.meet.nielitbhubaneswar.in"
    room_metadata_component = "metadata.meet.nielitbhubaneswar.in"
```

> **Important:** `app_id` and `app_secret` must **exactly match** the portal:
> - JWT App ID = `nielit_portal`
> - JWT App Secret = same string saved in **Admin → Online Classes**

See also: `jitsi-jwt-prosody.cfg.lua.example` in this folder.

---

## 3. Guest / anonymous access — disable

In the same file, find the **guest** VirtualHost and ensure guests cannot bypass JWT:

```lua
VirtualHost "guest.meet.nielitbhubaneswar.in"
    authentication = "jitsi-anonymous"
    c2s_require_encryption = false
```

With `allow_empty_token = false` on the main host, users **must** present a valid JWT from the portal.  
Do **not** give students the raw `https://meet.../ROOM` link — only the site join link.

---

## 4. Restart services (native install)

```bash
sudo systemctl restart prosody
sudo systemctl restart jicofo
sudo systemctl restart jitsi-videobridge2
sudo systemctl restart nginx
```

Check status:

```bash
sudo systemctl status prosody jicofo jitsi-videobridge2 nginx --no-pager
```

Logs if auth still fails:

```bash
sudo journalctl -u prosody -n 100 --no-pager
sudo journalctl -u jicofo -n 50 --no-pager
```

---

## 5. Portal settings (Hostinger)

In **Admin → Online Classes → Video Server Controls**:

| Setting | Value |
|---------|--------|
| Video backend | **NIELIT Server (meet.nielitbhubaneswar.in)** |
| Enable JWT | **On** |
| JWT App ID | `nielit_portal` |
| JWT App Secret | **same as Prosody `app_secret`** |

Students join only via:

```
https://nielitbhubaneswar.in/student/join_class?t=...
```

The portal checks student login + batch enrollment, then signs a short-lived JWT.

---

## 6. What the portal puts in the JWT

| Claim | Value |
|-------|--------|
| `iss` | `nielit_portal` (must match `app_id`) |
| `aud` | `jitsi` |
| `sub` | `meet.nielitbhubaneswar.in` (lowercase domain) |
| `room` | Specific class room, e.g. `NIELITBBSR...` |
| `context.user.moderator` | `true` for admin, `false` for students |
| `context.user.id` | `student:...` or `admin:...` |

Token is passed in the URL **hash**: `#jwt=...` (not only query string).

---

## 7. Recording (embedded portal)

| Type | Requirement |
|------|-------------|
| **Local recording** | Enabled via portal JWT `local-recording: true` + `custom-config.js` — saves to host PC |
| **Cloud recording** | Needs **Jibri** package on the server |

Without Jibri, moderators see **Start local recording** in the ⋮ menu (not cloud recording).

Copy updated web configs to the server:

```bash
sudo cp custom-config.js /usr/share/jitsi-meet-cfg/web/custom-config.js
sudo cp custom-interface_config.js /usr/share/jitsi-meet-cfg/web/custom-interface_config.js
sudo systemctl restart nginx
```

---

## 8. Troubleshooting "Authentication failed"

| Check | Action |
|-------|--------|
| Secret mismatch | Compare Prosody `app_secret` vs portal JWT App Secret character-by-character |
| Wrong `app_id` | Must be `nielit_portal` on both sides |
| Still `internal_hashed` | Change to `authentication = "token"` and restart prosody |
| `allow_empty_token = true` | Set to `false` |
| JWT disabled in portal | Enable JWT for NIELIT Server provider |
| Stale config | Restart all four services (section 4) |
| Room claim | Portal uses room-specific tokens — join only through portal link |

### Quick secret generator (on Jitsi server)

```bash
openssl rand -hex 32
```

Put the output in **both** Prosody `app_secret` and the portal JWT App Secret field.

---

## 9. Docker vs native — do not mix instructions

| | Docker | Native (your server) |
|---|--------|----------------------|
| JWT config | `/opt/jitsi-meet/.env` | `/etc/prosody/conf.avail/*.cfg.lua` |
| Restart | `docker compose restart` | `systemctl restart prosody jicofo ...` |
| Web custom JS | `/opt/jitsi-meet/config/web/` | `/usr/share/jitsi-meet-cfg/web/` |

Official native install: https://jitsi.github.io/handbook/docs/devops-guide/devops-guide-quickstart
