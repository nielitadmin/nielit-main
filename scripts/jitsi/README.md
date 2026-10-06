# Jitsi Meet on Linux (NIELIT Online Classes)

Self-host Jitsi on a **Linux VPS** for 100–450 students in **webinar mode** (1 teacher on video, students listen).

> Do **not** install on shared Hostinger web hosting. Use a VPS (Ubuntu 22.04/24.04 or Debian 12).

---

## 1. Server requirements

| Item | Minimum | Recommended (300+ students) |
|------|---------|-------------------------------|
| OS | Ubuntu 22.04+ / Debian 12 | Ubuntu 24.04 |
| CPU | 4 vCPU | 8+ vCPU |
| RAM | 8 GB | 16 GB |
| Disk | 40 GB SSD | 80 GB SSD |
| Upload | 100+ Mbps | 300+ Mbps |

---

## 2. DNS (before install)

Create an **A record**:

```
meet.nielitbhubaneswar.in  →  YOUR_VPS_PUBLIC_IP
```

Wait 5–30 minutes for DNS to propagate.

---

## 3. Quick install (automated)

On the **Linux VPS** as root:

```bash
# Copy scripts from your project to the server, e.g.:
# scp -r scripts/jitsi/ root@YOUR_VPS_IP:/root/jitsi/

cd /root/jitsi
chmod +x install-jitsi-docker.sh

export MEET_DOMAIN=meet.nielitbhubaneswar.in
export LETSENCRYPT_EMAIL=admin@nielitbhubaneswar.in
export PUBLIC_IP=YOUR_VPS_PUBLIC_IP

sudo bash install-jitsi-docker.sh
```

Or run interactively (script will ask for domain, email, IP):

```bash
sudo bash install-jitsi-docker.sh
```

---

## 4. Manual install (step by step)

```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg

# Docker (Ubuntu)
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Jitsi (pinned release — do not use git master)
sudo mkdir -p /opt/jitsi-meet && cd /opt/jitsi-meet
RELEASE=stable-11031
BASE=https://raw.githubusercontent.com/jitsi/docker-jitsi-meet/$RELEASE
sudo curl -fsSLo docker-compose.yml $BASE/docker-compose.yml
sudo curl -fsSL -o .env $BASE/env.example
sudo curl -fsSLo gen-passwords.sh $BASE/gen-passwords.sh
sudo chmod +x gen-passwords.sh
sudo ./gen-passwords.sh
```

Edit `/opt/jitsi-meet/.env` — set at least:

```env
CONFIG=/opt/jitsi-meet/config
HTTP_PORT=80
HTTPS_PORT=443
TZ=Asia/Kolkata
PUBLIC_URL=https://meet.nielitbhubaneswar.in
ENABLE_HTTP_REDIRECT=1
ENABLE_LETSENCRYPT=1
LETSENCRYPT_DOMAIN=meet.nielitbhubaneswar.in
LETSENCRYPT_EMAIL=admin@nielitbhubaneswar.in
JVB_ADVERTISE_IPS=YOUR_VPS_PUBLIC_IP
JITSI_IMAGE_VERSION=stable-11031
ENABLE_COLIBRI_WEBSOCKET=1
ENABLE_LOBBY=1
ENABLE_PREJOIN_PAGE=1
```

Copy NIELIT custom configs:

```bash
sudo mkdir -p /opt/jitsi-meet/config/web
sudo cp custom-config.js /opt/jitsi-meet/config/web/custom-config.js
sudo cp custom-interface_config.js /opt/jitsi-meet/config/web/custom-interface_config.js
```

Start:

```bash
cd /opt/jitsi-meet
sudo docker compose pull
sudo docker compose up -d
```

---

## 5. Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 10000/udp
sudo ufw enable
sudo ufw status
```

---

## 6. Connect NIELIT portal (Hostinger)

Edit `includes/online_class_config.php` on the main website:

```php
define('ONLINE_CLASS_JITSI_DOMAIN', 'meet.nielitbhubaneswar.in');
define('ONLINE_CLASS_VIDEO_MODE', 'open');   // full-page (recommended)
// define('ONLINE_CLASS_VIDEO_MODE', 'embed'); // video inside join_class.php
```

Deploy to production, then test:

1. Admin → Online Classes → schedule a class  
2. Student → Join Class → Enter Classroom  
3. Should open `https://meet.nielitbhubaneswar.in/NIELITBBSR...`

---

## 7. Useful commands

```bash
cd /opt/jitsi-meet
docker compose ps
docker compose logs -f web
docker compose logs -f jvb
docker compose restart
docker compose down
docker compose pull && docker compose up -d   # upgrade
```

---

## 8. Troubleshooting

| Problem | Fix |
|---------|-----|
| No video / one-way audio | Set `JVB_ADVERTISE_IPS` to VPS public IP; open UDP 10000 |
| Certificate error | DNS must point to server before first start; check `docker compose logs web` |
| Still uses meet.jit.si | Update `online_class_config.php` on Hostinger and redeploy |
| Room drops at 5 min | You are on public meet.jit.si embed — use self-hosted + `open` mode |
| 75 user limit | You are still on meet.jit.si — switch to self-hosted |

---

## 9. Files in this folder

| File | Purpose |
|------|---------|
| `install-jitsi-docker.sh` | One-command Linux installer |
| `custom-config.js` | Webinar defaults (muted join, lobby) |
| `custom-interface_config.js` | NIELIT branding / toolbar |
| `online_class_config.selfhosted.php.example` | Portal config snippet |

Official docs: https://jitsi.github.io/handbook/docs/devops-guide/devops-guide-docker
