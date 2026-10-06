// NIELIT — Jitsi custom config (mounted as custom-config.js)
// Webinar-friendly defaults: teacher presents, students join muted.

config.startWithAudioMuted = true;
config.startWithVideoMuted = true;
config.prejoinPageEnabled = true;
config.enableLobby = true;
config.enableGuestDomain = false;

// Reduce load for large listen-only audiences
config.channelLastN = 20;
config.startBitrate = '800';
config.disableSimulcast = false;

// Branding (optional — change title in interface_config.js too)
config.defaultLanguage = 'en';

// Recording / livestream (requires Jibri on the Jitsi server for cloud recording)
config.disableRecording = false;
config.fileRecordingsEnabled = true;
config.liveStreamingEnabled = true;
config.localRecording = {
    disable: false,
    notifyAllParticipants: true
};
