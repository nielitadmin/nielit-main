import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Loads the live portal (change PORTAL_URL for local XAMPP testing).
 * Set CAPACITOR_SERVER_URL env var when syncing, e.g.:
 *   set CAPACITOR_SERVER_URL=http://10.0.2.2/public_html&& npm run cap:sync
 */
const portalUrl =
  (typeof process !== 'undefined' && process.env?.CAPACITOR_SERVER_URL?.trim()) ||
  'https://nielitbhubaneswar.in';

const config: CapacitorConfig = {
  appId: 'in.nielitbhubaneswar.portal',
  appName: 'NIELIT Bhubaneswar',
  webDir: 'www',
  android: {
    allowMixedContent: false,
    captureInput: true,
    webContentsDebuggingEnabled: false,
  },
  server: {
    url: portalUrl.replace(/\/$/, ''),
    cleartext: portalUrl.startsWith('http://'),
    androidScheme: 'https',
  },
  plugins: {
    SplashScreen: {
      launchShowDuration: 1200,
      launchAutoHide: true,
      backgroundColor: '#0d47a1',
      showSpinner: true,
      spinnerColor: '#ffffff',
    },
    StatusBar: {
      style: 'LIGHT',
      backgroundColor: '#0d47a1',
    },
  },
};

export default config;