# 🎬 AI Flixs

**The streaming home for AI-made films.** Netflix meets Instagram for AI filmmakers — every creator gets a page, every film gets an audience. Watch free films (ad-supported) or unlock premieres pay-per-view.

🌐 **Live:** [aiflixs.com](https://aiflixs.com)

---

## Features

- **Google sign-in** — one-tap authentication (Google Identity Services)
- **Film submissions** — creators upload AI films up to **20 GB**, **1080p–4K**, one film per creator
- **Resumable uploads** — videos upload directly from the browser to a video CDN via TUS (survives dropped connections), then transcode automatically
- **Pay-per-view** — creators set their own price (free with ads, or $2.99–$100). A purchase unlocks the film for **7 days**
- **Watch pages** — adaptive streaming player; free films play instantly, paid films gate behind a Stripe Checkout unlock
- **Creator pages** — data-driven profiles with each creator's films, followers, and view counts
- **Review dashboard** — admin queue to approve/reject submissions before they go live
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
├── index.html          # Home / browse
├── about.html          # About
├── signup.html         # Google + email sign-up
├── upload.html         # Film submission (rule enforcement)
├── watch.html          # Player + pay-per-view unlock
├── creator.html        # Creator profile  (?u=<handle>)
├── admin.html          # Review dashboard (admins only)
├── assets/
│   ├── style.css       # Dark, minimal theme
│   ├── app.js          # Auth, film loading, checkout
│   ├── upload.js       # Client-side rule checks + TUS upload
│   └── config.example.js
└── api/
    ├── config.example.php   # <-- copy to config.php and fill in
    ├── db.php               # BunnyDB query helper
    ├── auth.php             # Google token verification + sessions
    ├── films.php            # Public film catalogue
    ├── submit.php           # Submission + rule enforcement
    ├── upload_begin.php     # Presigned Bunny Stream upload
    ├── upload_complete.php  # Mark uploaded -> pending review
    ├── watch.php            # Access control + player URL
    ├── creator.php          # Creator page data
    ├── checkout.php         # Stripe Checkout + 7-day unlock
    └── admin.php            # Review queue (approve/reject)
```

## Running it yourself

1. Copy the config templates and fill in your own credentials:
   ```sh
   cp api/config.example.php api/config.php
   cp assets/config.example.js assets/config.js
   ```
2. Create the database tables (BunnyDB / any libSQL or SQLite):
   `profiles`, `films` (unique `creator_id` enforces one-film-per-creator),
   `purchases`, `follows`.
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
