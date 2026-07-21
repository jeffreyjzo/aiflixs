<?php
// AI Flixs — server-side configuration TEMPLATE.
// Copy this file to `config.php` and fill in your own values.
// config.php is gitignored and must NEVER be committed — it holds live secrets.

// BunnyDB (libSQL over HTTP) — your edge database
const BUNNYDB_URL   = 'https://YOUR-DB-ID.lite.bunnydb.net/v2/pipeline';
const BUNNYDB_TOKEN = 'YOUR_BUNNYDB_TOKEN';

// Google sign-in: OAuth client ID (ends in .apps.googleusercontent.com).
// Must match GOOGLE_CLIENT_ID in assets/config.js.
const GOOGLE_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com';

// Bunny Stream (video hosting) — create a library at
// dash.bunny.net -> Stream -> Add Video Library.
const BUNNY_STREAM_LIBRARY_ID = 'YOUR_LIBRARY_ID';
const BUNNY_STREAM_API_KEY    = 'YOUR_STREAM_API_KEY';
const BUNNY_STREAM_CDN_HOST   = 'vz-xxxxxxxx.b-cdn.net';

// Stripe (pay-per-view). Use a RESTRICTED key (rk_live_) scoped to
// Checkout Sessions — never the full secret key (sk_live_).
const STRIPE_PUBLISHABLE_KEY = 'pk_live_YOUR_PUBLISHABLE_KEY';
const STRIPE_SECRET_KEY      = 'rk_live_YOUR_RESTRICTED_KEY';
const SITE_URL               = 'https://your-domain.com';

// Upload rules (server-side enforcement, mirrors assets/config.js)
const MAX_BYTES = 20 * 1024 * 1024 * 1024; // 20 GB
const MIN_RES   = 1080; // minimum 1080p
const MAX_RES   = 2160; // maximum 4K

// Pay-per-view rules
const PRICE_MIN_CENTS = 299;    // $2.99 minimum custom price
const PRICE_MAX_CENTS = 10000;  // $100 maximum
const UNLOCK_DAYS     = 7;      // a purchase unlocks the film for 7 days

// Review dashboard access — emails allowed to approve/reject films
const ADMIN_EMAILS = ['you@example.com'];

function start_session(): void {
  session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'httponly' => true, 'samesite' => 'Lax', 'secure' => isset($_SERVER['HTTPS'])]);
  session_start();
}

function json_out($data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}
