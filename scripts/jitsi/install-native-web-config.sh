#!/usr/bin/env bash
#
# Install NIELIT custom Jitsi web configs on native Debian/Ubuntu (apt) install.
# Run on the Jitsi VM — files are NOT in your home folder by default.
#
#   curl -fsSL https://raw.githubusercontent.com/nielitadmin/nielit-main/main/scripts/jitsi/install-native-web-config.sh | sudo bash
#
# Or from your PC (after git clone):
#   scp scripts/jitsi/install-native-web-config.sh user@meet:/tmp/
#   ssh user@meet 'sudo bash /tmp/install-native-web-config.sh'
#
set -euo pipefail

WEB_CFG_DIR="/usr/share/jitsi-meet-cfg/web"
MEET_DOMAIN="${MEET_DOMAIN:-meet.nielitbhubaneswar.in}"

echo "[nielit-jitsi] Installing custom web configs to ${WEB_CFG_DIR} ..."

mkdir -p "${WEB_CFG_DIR}"

cat > "${WEB_CFG_DIR}/custom-config.js" <<'EOF'
// NIELIT — Jitsi custom config
config.startWithAudioMuted = true;
config.startWithVideoMuted = true;
config.prejoinPageEnabled = true;
config.enableLobby = true;
config.enableGuestDomain = false;
config.channelLastN = 20;
config.startBitrate = '800';
config.disableSimulcast = false;
config.defaultLanguage = 'en';
config.disableRecording = false;
config.fileRecordingsEnabled = false;
config.liveStreamingEnabled = true;
config.localRecording = {
    disable: false,
    disableSelfRecording: false,
    notifyAllParticipants: true
};
config.recordings = {
    recordAudioAndVideo: true,
    suggestRecording: true
};
EOF

cat > "${WEB_CFG_DIR}/custom-interface_config.js" <<'EOF'
// NIELIT — Jitsi interface tweaks
interfaceConfig.APP_NAME = 'NIELIT Classroom';
interfaceConfig.NATIVE_APP_NAME = 'NIELIT Classroom';
interfaceConfig.MOBILE_APP_PROMO = false;
interfaceConfig.SHOW_JITSI_WATERMARK = false;
interfaceConfig.SHOW_WATERMARK_FOR_GUESTS = false;
interfaceConfig.DISABLE_JOIN_LEAVE_NOTIFICATIONS = true;
interfaceConfig.TOOLBAR_BUTTONS = [
    'microphone',
    'camera',
    'desktop',
    'fullscreen',
    'fodeviceselection',
    'hangup',
    'chat',
    'recording',
    'localrecording',
    'livestreaming',
    'raisehand',
    'participants-pane',
    'tileview',
    'security',
    'invite',
    'videoquality',
    'stats',
    'shortcuts',
    'profile',
    'settings'
];
EOF

chmod 644 "${WEB_CFG_DIR}/custom-config.js" "${WEB_CFG_DIR}/custom-interface_config.js"

echo "[nielit-jitsi] Files installed:"
ls -la "${WEB_CFG_DIR}/custom-"*.js

# Ensure main meet config loads custom files (some older installs)
MEET_CONFIG="/etc/jitsi/meet/${MEET_DOMAIN}-config.js"
if [[ -f "${MEET_CONFIG}" ]] && ! grep -q 'custom-config.js' "${MEET_CONFIG}"; then
    echo "[nielit-jitsi] Note: ${MEET_CONFIG} may need to reference custom-config.js"
    echo "         Standard Debian packages load ${WEB_CFG_DIR} automatically."
fi

if systemctl is-active --quiet nginx; then
    echo "[nielit-jitsi] Restarting nginx ..."
    systemctl restart nginx
fi

echo "[nielit-jitsi] Done. Hard-refresh the browser (Ctrl+F5) after portal deploy."
echo "[nielit-jitsi] JWT must still match: Admin → Online Classes ↔ Prosody app_secret"
