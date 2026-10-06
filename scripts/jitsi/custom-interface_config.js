// NIELIT — Jitsi interface tweaks (copy to /opt/jitsi-meet/config/web/custom-interface_config.js)
interfaceConfig.APP_NAME = 'NIELIT Classroom';
interfaceConfig.NATIVE_APP_NAME = 'NIELIT Classroom';
interfaceConfig.MOBILE_APP_PROMO = false;
interfaceConfig.SHOW_JITSI_WATERMARK = false;
interfaceConfig.SHOW_WATERMARK_FOR_GUESTS = false;
interfaceConfig.DISABLE_JOIN_LEAVE_NOTIFICATIONS = true;

// Full toolbar for hosts (moderators get recording via JWT features claim)
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
