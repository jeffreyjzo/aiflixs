# 🎬 AI Flixs

**The streaming home for AI-made films.** Netflix meets Instagram for AI filmmakers — every creator gets a page, every film gets an audience. Watch free films (ad-supported) or unlock premieres pay-per-view.

🌐 **Live:** [aiflixs.com](https://aiflixs.com)

---

## Features

- **Google sign-in** — one-tap authentication, with a watch-or-publish choice at sign-up
- **Two experiences** — a viewer profile (watch history, active unlocks, suggestions) and a **Creator Studio** (analytics, earnings, built-in uploader)
- **Film submissions** — up to **20 GB**, **1080p–4K**, vertical *and* horizontal; per-creator film slots an admin can raise
- **Resumable uploads** — videos go straight from the browser to a video CDN via TUS (survives dropped connections), then transcode automatically
- **Pay-per-view** — creators set their own price (free with ads, or $2.99–$100). A purchase unlocks the film for **7 days**
- **Watch pages** — adaptive streaming player with a portrait layout for vertical films; paid films gate behind Stripe Checkout
- **Creator pages** — data-driven public profiles with films, followers, and view counts
- **Admin dashboard** — business metrics, a click-to-play review queue, every video's storage location, and per-account controls (slots, creator status, suspension)
- **Motion design** — animated neural-network hero, decoding headline, scroll reveals, parallax and a scroll-progress bar, all respecting `prefers-reduced-motion`
- **Free + ads model** — AdSense-ready ad slots under free films

## Tech stack

| Layer | Technology |
|---|---|
| Frontend | Vanilla HTML/CSS/JS — no framework, no build step |
| Backend API | PHP (shared hosting friendly) |
| Database | [BunnyDB](https://bunny.net) (libSQL / SQLite over HTTP) |
| Video hosting | [Bunny Stream](https://bunny.net) — resumable upload, transcoding, global CDN |
| Auth | Google Identity Services (OAuth) |
| Payments | [Stripe](https://stripe.com) Checkout (restricted keys) |

## Project structure

```
aiflixs/
├── index.html          # Home / browse (animated hero)
├── about.html          # About
├── signup.html         # Sign-up: choose watch or publish, then Google
├── upload.html         # Creator landing page (routes creators to the studio)
├── dashboard.html      # Creator Studio: analytics + uploader
├── member.html         # Viewer profile: history, unlocks, suggestions
├── watch.html          # Player + pay-per-view unlock
├── creator.html        # Public creator profile  (?u=<handle>)
├── admin.php           # Admin dashboard — server-side gated to admin emails
├── assets/
│   ├── style.css       # Black / electric-blue / red theme
│   ├── fx.js           # Motion layer: neural canvas, reveals, parallax
│   ├── app.js          # Auth, role-aware nav, film loading, checkout
│   ├── upload.js       # Client-side rule checks + TUS upload
│   ├── logo.svg, favicon.svg
│   └── config.example.js
└── api/
    ├── config.example.php   # <-- copy to config.php and fill in
    ├── db.php               # BunnyDB query helper
    ├── auth.php             # Google token verification + sessions
    ├── profile.php          # Creator mode, profile edits
    ├── films.php            # Public film catalogue
    ├── submit.php           # Submission + rule/slot enforcement
    ├── upload_begin.php     # Presigned Bunny Stream upload
    ├── upload_complete.php  # Mark uploaded -> pending review
    ├── watch.php            # Access control + player URL + watch history
    ├── creator.php          # Public creator page data
    ├── dashboard.php        # Creator Studio data
    ├── member.php           # Viewer profile data
    ├── checkout.php         # Stripe Checkout + 7-day unlock
    ├── metrics.php          # Business metrics (admin)
    └── admin.php            # Review queue, accounts, admin actions
```

## Running it yourself

1. Copy the config templates and fill in your own credentials:
   ```sh
   cp api/config.example.php api/config.php
   cp assets/config.example.js assets/config.js
   ```
2. Create the database tables (BunnyDB / any libSQL or SQLite):
   `profiles` (with `is_creator`, `film_slots`, `suspended`), `films`,
   `purchases`, `follows`, `watch_history`.
3. Set up a [Google OAuth client](https://console.cloud.google.com) and a
   [Bunny Stream](https://dash.bunny.net) library; add the IDs/keys to `config.php`.
4. Serve the folder with PHP:
   ```sh
   php -S localhost:8000
   ```

> **Security note:** `api/config.php` and `assets/config.js` are gitignored —
> they hold live credentials and must never be committed. Only the `.example`
> templates belong in version control. Stripe integration uses a **restricted**
> key scoped to Checkout Sessions.

## License

© 2026 AI Flixs. All rights reserved.
