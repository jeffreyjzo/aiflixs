// AI Flixs — frontend configuration TEMPLATE.
// Copy this file to `config.js` and fill in your own values.
// config.js is gitignored. (These are public-by-design identifiers, not
// secret keys, but we keep the real file out of git for cleanliness.)
window.AF_CONFIG = {
  API_BASE: "api",

  // Google sign-in OAuth client ID (same value as api/config.php)
  GOOGLE_CLIENT_ID: "YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com",

  // Free + ads model: your Google AdSense client ID (ca-pub-XXXX) once approved
  ADSENSE_CLIENT: "",

  // Video upload rules (also enforced server-side in api/submit.php)
  MAX_BYTES: 20 * 1024 * 1024 * 1024, // 20 GB
  MIN_RES: 1080,                       // minimum resolution (short side, px)
  MAX_RES: 2160,                       // maximum resolution: 4K (short side, px)
  MAX_VIDEOS_PER_CREATOR: 1,

  // Pay-per-view rules (mirrors server)
  PRICE_MIN_CENTS: 299,   // $2.99
  PRICE_MAX_CENTS: 10000, // $100
  UNLOCK_DAYS: 7,
};
