// AI Flixs — shared app logic: Google sign-in + session
(function () {
  const cfg = window.AF_CONFIG || {};
  const live = !!cfg.GOOGLE_CLIENT_ID;

  window.AF = {
    live,
    user: null,

    async init() {
      this.loadFilms();
      if (!live) return this.showDemoBanner();

      // Load current session
      try {
        const r = await fetch(cfg.API_BASE + "/auth.php");
        this.user = (await r.json()).user;
        if (this.user) this.reflectUser();
      } catch (_) {}

      // Load Google Identity Services
      const s = document.createElement("script");
      s.src = "https://accounts.google.com/gsi/client";
      s.async = true;
      s.onload = () => {
        google.accounts.id.initialize({
          client_id: cfg.GOOGLE_CLIENT_ID,
          callback: (resp) => this.onGoogleCredential(resp),
        });
        // Render Google's official button (popup flow — works even when
        // One Tap / FedCM is unavailable). Falls back to the styled button.
        const slot = document.getElementById("gsi-button");
        if (slot) {
          google.accounts.id.renderButton(slot, {
            theme: "filled_black", size: "large", shape: "pill",
            text: "continue_with", width: 360,
          });
          const fb = document.getElementById("google-fallback");
          if (fb && slot.childNodes.length) fb.style.display = "none";
        }
      };
      document.head.appendChild(s);
    },

    // Swap the homepage showcase for real films once creators have published
    async loadFilms() {
      const grid = document.getElementById("trending-grid");
      if (!grid) return;
      const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
      try {
        const r = await fetch(cfg.API_BASE + "/films.php");
        const films = (await r.json()).films || [];
        if (!films.length) return; // keep showcase content until real films exist
        const g = ["g1", "g2", "g3", "g4", "g5", "g6"];
        grid.innerHTML = films.map((f, i) => {
          const price = Number(f.price_cents) === 0
            ? '<span class="badge free">FREE</span>'
            : '<span class="badge">PPV · $' + (f.price_cents / 100).toFixed(2) + "</span>";
          return '<a class="film-card" href="watch.html?film=' + f.id + '">' +
            '<div class="poster ' + g[i % 6] + '">' + price + "</div>" +
            '<div class="film-meta"><h3>' + esc(f.title) + "</h3><p>by @" + esc(f.handle) + "</p></div></a>";
        }).join("");
      } catch (_) {}
    },

    // Pay-per-view: create a Stripe Checkout session and send the viewer there
    async unlockFilm(filmId) {
      if (!this.live) return this.demoNotice("Checkout (Stripe)");
      if (!this.user) { location.href = "signup.html"; return; }
      const r = await fetch(cfg.API_BASE + "/checkout.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ film_id: filmId }),
      });
      const d = await r.json();
      if (d.url) location.href = d.url;
      else if (d.error === "stripe_not_configured") alert("Payments are almost live — check back soon.");
      else if (d.error === "already_owned") alert("You already own this film — enjoy!");
      else if (d.error === "film_not_found") alert("This film isn't available for purchase yet.");
      else alert("Checkout failed: " + (d.error || "unknown error"));
    },

    signInWithGoogle() {
      if (!live) return this.demoNotice("Google sign-in");
      google.accounts.id.prompt();
    },

    async onGoogleCredential(resp) {
      const r = await fetch(cfg.API_BASE + "/auth.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ credential: resp.credential }),
      });
      const d = await r.json();
      if (d.user) {
        this.user = d.user;
        this.reflectUser();
        if (location.pathname.endsWith("signup.html")) location.href = "index.html";
      } else {
        alert("Sign-in failed: " + (d.error || "unknown error"));
      }
    },

    async signOut() {
      await fetch(cfg.API_BASE + "/auth.php", { method: "DELETE" });
      location.reload();
    },

    signUpWithEmail() {
      alert("Email sign-up is coming soon — please use Google for now.");
    },

    reflectUser() {
      const btn = document.querySelector(".nav-links .btn");
      if (btn && this.user) {
        btn.textContent = this.user.display_name || "Account";
        btn.href = "#";
        btn.onclick = (e) => { e.preventDefault(); if (confirm("Sign out?")) this.signOut(); };
      }
    },

    demoNotice(feature) {
      alert(
        feature + " is not wired up yet - this is the demo build.\n\n" +
        "Add your Google OAuth client ID in assets/config.js and api/config.php to go live."
      );
    },

    showDemoBanner() {
      const add = () => {
        const b = document.createElement("div");
        b.className = "demo-banner";
        b.innerHTML =
          "<strong>Demo mode</strong> &mdash; add the Google client ID in <code>assets/config.js</code> to enable sign-ins.";
        document.body.appendChild(b);
      };
      document.readyState === "loading"
        ? document.addEventListener("DOMContentLoaded", add)
        : add();
    },
  };

  document.readyState === "loading"
    ? document.addEventListener("DOMContentLoaded", () => AF.init())
    : AF.init();
})();
