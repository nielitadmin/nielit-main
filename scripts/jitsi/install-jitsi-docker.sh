#!/usr/bin/env bash
#
# NIELIT — Jitsi Meet on Linux (Docker, official docker-jitsi-meet release)
#
# Tested on: Ubuntu 22.04 / 24.04, Debian 12
# Run as root: sudo bash install-jitsi-docker.sh
#
# Before running:
#   1. Point DNS A record: meet.yourdomain.in → this server's public IP
#   2. Open firewall: 80/tcp, 443/tcp, 10000/udp (and 4443/tcp if needed)
#
set -euo pipefail

INSTALL_DIR="/opt/jitsi-meet"
JITSI_RELEASE="${JITSI_RELEASE:-stable-11031}"
TZ_VALUE="${TZ_VALUE:-Asia/Kolkata}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[jitsi]${NC} $*"; }
warn() { echo -e "${YELLOW}[warn]${NC} $*"; }
die()  { echo -e "${RED}[error]${NC} $*" >&2; exit 1; }

if [[ "${EUID:-0}" -ne 0 ]]; then
  die "Run as root: sudo bash $0"
fi

if [[ -z "${MEET_DOMAIN:-}" ]]; then
  read -r -p "Jitsi domain (e.g. meet.nielitbhubaneswar.in): " MEET_DOMAIN
fi
MEET_DOMAIN="$(echo "$MEET_DOMAIN" | tr -d '[:space:]' | sed 's#^https\?://##;s#/$##')"
[[ -n "$MEET_DOMAIN" ]] || die "Domain is required."

if [[ -z "${LETSENCRYPT_EMAIL:-}" ]]; then
  read -r -p "Let's Encrypt email: " LETSENCRYPT_EMAIL
fi
[[ -n "$LETSENCRYPT_EMAIL" ]] || die "Email is required for HTTPS certificate."

PUBLIC_IP="${PUBLIC_IP:-}"
if [[ -z "$PUBLIC_IP" ]]; then
  PUBLIC_IP="$(curl -fsS --max-time 8 https://api.ipify.org 2>/dev/null || true)"
  if [[ -z "$PUBLIC_IP" ]]; then
    read -r -p "Server public IP (for JVB_ADVERTISE_IPS): " PUBLIC_IP
  else
    log "Detected public IP: $PUBLIC_IP"
  fi
fi
[[ -n "$PUBLIC_IP" ]] || die "Public IP is required."

log "Installing Docker (if missing)..."
if ! command -v docker >/dev/null 2>&1; then
  apt-get update -qq
  apt-get install -y ca-certificates curl gnupg
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
  chmod a+r /etc/apt/keyrings/docker.gpg
  echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
    $(. /etc/os-release && echo "${VERSION_CODENAME:-$VERSION_ID}") stable" \
    > /etc/apt/sources.list.d/docker.list
  apt-get update -qq
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
fi

docker compose version >/dev/null 2>&1 || die "docker compose plugin not found."

log "Downloading Jitsi release: $JITSI_RELEASE"
mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

BASE="https://raw.githubusercontent.com/jitsi/docker-jitsi-meet/${JITSI_RELEASE}"
curl -fsSLo docker-compose.yml "$BASE/docker-compose.yml"
curl -fsSLo .env "$BASE/env.example"
curl -fsSLo gen-passwords.sh "$BASE/gen-passwords.sh"
chmod +x gen-passwords.sh

log "Generating internal passwords..."
./gen-passwords.sh

CONFIG_DIR="${INSTALL_DIR}/config"
mkdir -p "$CONFIG_DIR"

# Patch .env for production
patch_env() {
  local key="$1"
  local val="$2"
  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${val}|" .env
  else
    echo "${key}=${val}" >> .env
  fi
}

patch_env "CONFIG" "$CONFIG_DIR"
patch_env "HTTP_PORT" "80"
patch_env "HTTPS_PORT" "443"
patch_env "TZ" "$TZ_VALUE"
patch_env "PUBLIC_URL" "https://${MEET_DOMAIN}"
patch_env "ENABLE_HTTP_REDIRECT" "1"
patch_env "ENABLE_LETSENCRYPT" "1"
patch_env "LETSENCRYPT_DOMAIN" "$MEET_DOMAIN"
patch_env "LETSENCRYPT_EMAIL" "$LETSENCRYPT_EMAIL"
patch_env "JVB_ADVERTISE_IPS" "$PUBLIC_IP"
patch_env "JITSI_IMAGE_VERSION" "$JITSI_RELEASE"
patch_env "ENABLE_COLIBRI_WEBSOCKET" "1"
patch_env "ENABLE_XMPP_WEBSOCKET" "1"
patch_env "ENABLE_GUESTS" "1"

# Large webinar-style classes (teacher video, many listeners)
patch_env "ENABLE_LOBBY" "1"
patch_env "ENABLE_PREJOIN_PAGE" "1"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "${SCRIPT_DIR}/custom-config.js" ]]; then
  mkdir -p "${CONFIG_DIR}/web"
  cp "${SCRIPT_DIR}/custom-config.js" "${CONFIG_DIR}/web/custom-config.js"
  log "Installed custom-config.js (webinar defaults)."
fi
if [[ -f "${SCRIPT_DIR}/custom-interface_config.js" ]]; then
  mkdir -p "${CONFIG_DIR}/web"
  cp "${SCRIPT_DIR}/custom-interface_config.js" "${CONFIG_DIR}/web/custom-interface_config.js"
  log "Installed custom-interface_config.js."
fi

log "Starting Jitsi containers..."
docker compose pull
docker compose up -d

log "Waiting for web container..."
sleep 15

if docker compose ps --status running | grep -q web; then
  log "Jitsi is running."
else
  warn "Some containers may still be starting. Check: cd $INSTALL_DIR && docker compose ps"
fi

cat <<EOF

================================================================================
  Jitsi Meet installed
================================================================================
  URL:        https://${MEET_DOMAIN}
  Install:    ${INSTALL_DIR}
  Config:     ${CONFIG_DIR}

  Firewall (required):
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw allow 10000/udp

  NIELIT portal — edit includes/online_class_config.php on Hostinger:
    define('ONLINE_CLASS_JITSI_DOMAIN', '${MEET_DOMAIN}');
    define('ONLINE_CLASS_VIDEO_MODE', 'open');   // or 'embed'

  Useful commands:
    cd ${INSTALL_DIR}
    docker compose ps
    docker compose logs -f web
    docker compose restart
    docker compose down

  Test: open https://${MEET_DOMAIN} and create a room with 3+ users.
================================================================================
EOF
