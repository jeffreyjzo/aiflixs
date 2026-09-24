// AI Flixs — shared app logic: Google sign-in, session, role-aware nav
(function () {
  const cfg = window.AF_CONFIG || {};
  const live = !!cfg.GOOGLE_CLIENT_ID;

  window.AF = {
    live,
    user: null,

    get isCreator() {
      return !!(this.user && Number(this.user.is_creator) === 1);
    },

    async init() {
      this.loadFilms();
      if (!live) return this.showDemoBanner();

      try {
        const r = await fetch(cfg.API_BASE + "/auth.php");
        this.user = (await r.json()).user;
      } catch (_) {}
      this.reflectUser();

      const s = document.createElement("script");
      s.src = "https://accounts.google.com/gsi/client";
      s.async = true;
      s.onload = () => {
        google.accounts.id.initialize({
          client_id: cfg.GOOGLE_CLIENT_ID,
          callback: (resp) => this.onGoogleCredential(resp),
        });
        const holder = document.getElementById("gsi-button");
        if (holder) {
          google.accounts.id.renderButton(holder, {
            theme: "filled_black", size: "large", shape: "pill",
            text: "continue_with", width: 320,
          });
          const fb = document.getElementById("google-fallback");
          if (fb) fb.style.display = "none";
        }
      };
      document.head.appendChild(s);
    },

    signInWithGoogle() {
      if (!live) return this.demoNotice("Google sign-in");
      google.accounts.id.prompt();
    },

    // Remember whether they signed up to watch or to create
    setIntent(role) {
      try { sessionStorage.setItem("af_intent", role); } catch (_) {}
    },

    async onGoogleCredential(resp) {
      const r = await fetch(cfg.API_BASE + "/auth.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ credential: resp.credential }),
      });
      const d = await r.json();
      if (!d.user) return alert("Sign-in failed: " + (d.error || "unknown error"));
      this.user = d.user;

      let intent = null;
      try { intent = sessionStorage.getItem("af_intent"); sessionStorage.removeItem("af_intent"); } catch (_) {}

      if (intent === "creator" && !this.isCreator) {
        const p = await fetch(cfg.API_BASE + "/profile.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ become_creator: true }),
        });
        const pd = await p.json();
        if (pd.user) this.user = pd.user;
      }

      this.routeAfterAuth(intent);
    },

    routeAfterAuth(intent) {
      const onAuthPage = /signup\.html$/.test(location.pathname) || location.pathname.endsWith("/signup.html");
      if (intent === "creator" || this.isCreator) {
        if (onAuthPage) location.href = "dashboard.html";
        else location.reload();
        return;
      }
      if (onAuthPage) location.href = "index.html";
      else location.reload();
    },

    async becomeCreator() {
      if (!this.live) return this.demoNotice("Creator mode");
      if (!this.user) { this.setIntent("creator"); location.href = "signup.html"; return; }
      const r = await fetch(cfg.API_BASE + "/profile.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ become_creator: true }),
      });
      const d = await r.json();
      if (d.user) { this.user = d.user; location.href = "dashboard.html"; }
      else alert("Couldn't switch on creator mode: " + (d.error || "unknown"));
    },

    async signOut() {
      await fetch(cfg.API_BASE + "/auth.php", { method: "DELETE" });
      location.href = "index.html";
    },

    signUpWithEmail() {
      alert("Email sign-up is coming soon — please use Google for now.");
    },

    // Swap the "Sign in" button for the signed-in user's own menu, and
    // surface Creator Studio only to creators.
    reflectUser() {
      const nav = document.querySelector(".nav-links");
      if (!nav || !this.user) return;

      const signIn = nav.querySelector('a[href="signup.html"]');
      if (signIn) {
        signIn.textContent = this.user.display_name || "My profile";
        signIn.href = "member.html";
        signIn.onclick = null;
      }

      if (this.isCreator && !nav.querySelector('a[href="dashboard.html"]')) {
        const studio = document.createElement("a");
        studio.href = "dashboard.html";
        studio.textContent = "Creator Studio";
        studio.className = "hide-sm";
        nav.insertBefore(studio, signIn || null);
      }

      // Viewers see "Become a creator" instead of a raw submit link
      const submit = nav.querySelector('a[href="upload.html"]');
      if (submit && !this.isCreator) {
        submit.textContent = "Become a creator";
        submit.href = "#";
        submit.onclick = (e) => { e.preventDefault(); this.becomeCreator(); };
      }
    },

    // Homepage grid — real films replace the placeholder once published
    async loadFilms() {
      const grid = document.getElementById("trending-grid");
      if (!grid) return;
      const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
      try {
        const r = await fetch(cfg.API_BASE + "/films.php");
        const films = (await r.json()).films || [];
        if (!films.length) return;
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
      else if (d.error === "already_owned") alert("You already have access to this film — enjoy!");
      else if (d.error === "film_not_found") alert("This film isn't available for purchase yet.");
      else alert("Checkout failed: " + (d.error || "unknown error"));
    },

    demoNotice(feature) {
      alert(feature + " is not wired up yet - this is the demo build.");
    },

    showDemoBanner() {
      const add = () => {
        const b = document.createElement("div");
        b.className = "demo-banner";
        b.innerHTML = "<strong>Demo mode</strong> &mdash; add the Google client ID in <code>assets/config.js</code>.";
        document.body.appendChild(b);
      };
      document.readyState === "loading"
        ? document.addEventListener("DOMContentLoaded", add) : add();
    },
  };

  document.readyState === "loading"
    ? document.addEventListener("DOMContentLoaded", () => AF.init())
    : AF.init();
})();
