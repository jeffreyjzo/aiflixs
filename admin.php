<?php
// Server-side admin gate: non-admins never receive this page at all.
require_once __DIR__ . '/api/db.php';
start_session();

$is_admin = false;
if (!empty($_SESSION['uid'])) {
  try {
    $me = db_query('SELECT email FROM profiles WHERE id = ?', [$_SESSION['uid']]);
    if ($me && in_array(strtolower($me[0]['email']), array_map('strtolower', ADMIN_EMAILS), true)) {
      $is_admin = true;
    }
  } catch (Throwable $e) { /* fall through to redirect */ }
}
if (!$is_admin) {
  header('Location: ' . (empty($_SESSION['uid']) ? '/signup.html' : '/index.html'));
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin — AI Flixs</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/style.css" />
  <style>
    .admin-wrap { max-width: 1080px; margin: 0 auto; padding: 110px 20px 80px; }
    .admin-wrap h1 { font-size: 30px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 4px; }
    .admin-wrap .sub { color: var(--muted); margin-bottom: 24px; font-size: 14px; }
    .tabs { display: flex; gap: 10px; margin-bottom: 26px; flex-wrap: wrap; }
    .alert {
      background: rgba(224,36,43,.12); border: 1px solid var(--accent);
      border-radius: 12px; padding: 14px 18px; margin-bottom: 22px; font-size: 14px;
    }
    .alert a { color: var(--accent-hover); font-weight: 600; }
    .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 30px; }
    .kpi { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 18px; }
    .kpi strong { display: block; font-size: 26px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 2px; }
    .kpi span { font-size: 12.5px; color: var(--muted); }
    .kpi .delta { font-size: 11.5px; color: #7dd97d; display: block; margin-top: 4px; }
    .kpi.hero strong { color: #7dd97d; }
    .kpi.warn strong { color: var(--accent-hover); }
    .kpi.click { cursor: pointer; transition: border-color .15s, transform .15s; }
    .kpi.click:hover { border-color: var(--accent); transform: translateY(-2px); }

    /* ---- Account controls ---- */
    .acct-ctl { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding: 14px; background: var(--surface-2); border-radius: 10px; }
    .acct-ctl label { font-size: 12.5px; color: var(--muted); font-weight: 600; }
    .acct-ctl input[type="number"] {
      width: 80px; background: var(--bg); border: 1px solid var(--border); color: var(--text);
      border-radius: 8px; padding: 8px 10px; font: 400 14px var(--font);
    }
    .acct-film {
      display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
      padding: 12px 14px; background: var(--surface-2); border-radius: 10px; margin-top: 8px; font-size: 13.5px;
    }
    .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 22px; margin-bottom: 18px; }
    .panel h2 { font-size: 16px; font-weight: 700; margin-bottom: 14px; }
    .rows { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; }
    .row-item { display: flex; justify-content: space-between; padding: 9px 12px; background: var(--surface-2); border-radius: 8px; font-size: 13.5px; }
    .row-item span { color: var(--muted); }
    .row-item b { font-weight: 700; }
    table.t { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    table.t th { text-align: left; color: var(--muted); font-size: 11.5px; text-transform: uppercase; letter-spacing: 1px; padding: 8px 10px; border-bottom: 1px solid var(--border); }
    table.t td { padding: 10px; border-bottom: 1px solid var(--border); }
    table.t tr:last-child td { border-bottom: 0; }
    .chart { display: flex; align-items: flex-end; gap: 5px; height: 110px; padding-top: 10px; }
    .chart .bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 5px; }
    .chart .bar { width: 100%; background: var(--accent); border-radius: 4px 4px 0 0; min-height: 3px; }
    .chart .lbl { font-size: 9.5px; color: var(--muted); }
    .queue-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 20px; }
    .queue-card h2 { font-size: 18px; }
    .queue-card .info { color: var(--muted); font-size: 13px; margin: 4px 0 14px; }
    .queue-card iframe { width: 100%; aspect-ratio: 16/9; border: 0; border-radius: 10px; background: #000; margin-bottom: 14px; }
    .queue-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-approve { background: #1d7a3d; color: #fff; }
    .btn-approve:hover { background: #249649; }
    .btn-reject { background: transparent; color: var(--accent-hover); border-color: var(--accent); }
    .pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; background: var(--surface-2); color: var(--muted); margin-left: 8px; }
    .pill.live { background: rgba(29,122,61,.25); color: #7dd97d; }
    .pill.wait { background: rgba(224,36,43,.15); color: var(--accent-hover); }

    /* ---- Review card with click-to-play thumbnail ---- */
    .rev { display: flex; gap: 20px; flex-wrap: wrap; }
    .rev-thumb {
      position: relative; flex: 0 0 auto; width: 260px; aspect-ratio: 16/9;
      border-radius: 10px; overflow: hidden; cursor: pointer;
      background: #000 center/cover no-repeat; border: 1px solid var(--border);
    }
    .rev-thumb.vert { width: 150px; aspect-ratio: 9/16; }
    .rev-thumb .play {
      position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
      background: rgba(0,0,0,.35); transition: background .15s; font-size: 44px; color: #fff;
      text-shadow: 0 2px 14px rgba(0,0,0,.6);
    }
    .rev-thumb:hover .play { background: rgba(0,0,0,.15); }
    .rev-thumb .enc {
      position: absolute; left: 8px; bottom: 8px; z-index: 2;
      background: rgba(0,0,0,.75); backdrop-filter: blur(4px);
      font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 999px;
    }
    .rev-thumb .enc.ok { color: #7dd97d; }
    .rev-thumb .enc.busy { color: #ffd479; }
    .rev-thumb.dead { cursor: not-allowed; }
    .rev-body { flex: 1 1 320px; min-width: 260px; }

    /* ---- Streaming modal ---- */
    .player-back {
      position: fixed; inset: 0; z-index: 200; display: none;
      background: rgba(0,0,0,.86); backdrop-filter: blur(6px);
      align-items: center; justify-content: center; padding: 24px;
    }
    .player-back.open { display: flex; }
    .player-box { width: 100%; max-width: 940px; max-height: 100%; overflow-y: auto; }
    .player-box.vert { max-width: 440px; }
    .player-box iframe {
      width: 100%; aspect-ratio: 16/9; border: 0; border-radius: 14px; background: #000; display: block;
    }
    .player-box.vert iframe { aspect-ratio: 9/16; }
    .player-head { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 12px; }
    .player-head h2 { font-size: 19px; font-weight: 700; }
    .player-head .x {
      background: none; border: 0; color: var(--muted); font-size: 28px; cursor: pointer; line-height: 1; padding: 0 6px;
    }
    .player-head .x:hover { color: var(--text); }
    .player-meta { color: var(--muted); font-size: 13px; margin: 12px 0 16px; }
    .player-actions { display: flex; gap: 10px; flex-wrap: wrap; }
  </style>
</head>
<body>
  <nav class="nav">
    <a href="index.html" class="logo">AI FLI<span class="x">X</span>S<span class="logo-tag">AI · FILM · FUTURE</span></a>
    <div class="nav-links">
      <a href="index.html">Browse</a>
      <a href="admin.php" class="active">Admin</a>
    </div>
  </nav>

  <main class="admin-wrap">
    <h1>Admin dashboard</h1>
    <p class="sub" id="stamp">Loading…</p>

    <div class="tabs">
      <button class="btn btn-primary" id="tab-overview" onclick="showTab('overview')">📊 Business overview</button>
      <button class="btn btn-ghost" id="tab-queue" onclick="showTab('queue')">🎬 Review queue</button>
      <button class="btn btn-ghost" id="tab-videos" onclick="showTab('videos')">🎞️ All videos</button>
      <button class="btn btn-ghost" id="tab-accounts" onclick="showTab('accounts')">👥 Accounts</button>
      <a class="btn btn-ghost" href="https://dash.bunny.net/stream" target="_blank" rel="noopener">Bunny ↗</a>
      <a class="btn btn-ghost" href="https://dashboard.stripe.com" target="_blank" rel="noopener">Stripe ↗</a>
    </div>

    <div id="overview"><p style="color:var(--muted)">Loading metrics…</p></div>
    <div id="queue" style="display:none"><p style="color:var(--muted)">Loading…</p></div>
    <div id="videos" style="display:none"><p style="color:var(--muted)">Loading…</p></div>
    <div id="accounts" style="display:none"><p style="color:var(--muted)">Loading…</p></div>
  </main>

  <!-- Streaming review window -->
  <div class="player-back" id="player-back" onclick="if(event.target===this)closePlayer()">
    <div class="player-box" id="player-box">
      <div class="player-head">
        <h2 id="pl-title">—</h2>
        <button class="x" onclick="closePlayer()" title="Close (Esc)">&times;</button>
      </div>
      <div id="pl-frame"></div>
      <p class="player-meta" id="pl-meta"></p>
      <div class="player-actions" id="pl-actions"></div>
    </div>
  </div>

  <script src="assets/config.js?v=11"></script>
  <script src="assets/app.js?v=11"></script>
  <script>
    const API = window.AF_CONFIG.API_BASE;
    const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    const gb = (b) => (b / 1024 ** 3).toFixed(2) + " GB";
    const money = (c) => "$" + (c / 100).toFixed(2);
    const dur = (sec) => {
      if (!sec) return "0m";
      const h = Math.floor(sec / 3600), m = Math.round((sec % 3600) / 60);
      return h ? h + "h " + m + "m" : m + "m";
    };
    const denied = (el, d) => {
      if (d.error === "not_signed_in") {
        el.innerHTML = '<p style="color:var(--muted)">Please <a href="signup.html" style="color:var(--accent)">sign in</a> with the admin account.</p>';
        return true;
      }
      if (d.error === "forbidden") {
        el.innerHTML = '<p style="color:var(--muted)">This account doesn\'t have admin access.</p>';
        return true;
      }
      if (d.error) { el.innerHTML = '<p style="color:var(--muted)">Error: ' + esc(d.error) + "</p>"; return true; }
      return false;
    };

    let loaded = { overview: false, queue: false, videos: false, accounts: false };
    let acctFilter = "all";
    function showTab(t) {
      ["overview", "queue", "videos", "accounts"].forEach((k) => {
        document.getElementById(k).style.display = k === t ? "" : "none";
        document.getElementById("tab-" + k).className = "btn " + (k === t ? "btn-primary" : "btn-ghost");
      });
      if (!loaded[t]) {
        loaded[t] = true;
        ({ overview: loadOverview, queue: loadQueue, videos: loadVideos, accounts: loadAccounts }[t])();
      }
    }
    // KPI tiles drill into the accounts list, pre-filtered
    function drillAccounts(filter) {
      acctFilter = filter;
      loaded.accounts = false;
      showTab("accounts");
    }

    // ---------- Business overview ----------
    async function loadOverview() {
      const el = document.getElementById("overview");
      const d = await (await fetch(API + "/metrics.php")).json();
      if (denied(el, d)) { document.getElementById("stamp").textContent = ""; return; }
      document.getElementById("stamp").textContent = "Updated " + d.generated_at;

      const p = d.people, c = d.content, e = d.engagement, r = d.revenue;
      const watchTime = d.stream.available ? dur(d.stream.watch_time_seconds) : "—";

      let html = "";
      if (d.needs_action > 0) {
        html += '<div class="alert">⚠️ <strong>' + d.needs_action + " film" + (d.needs_action > 1 ? "s" : "") +
          ' awaiting your review.</strong> <a href="#" onclick="showTab(\'queue\');return false;">Open the review queue →</a></div>';
      }

      html +=
        '<div class="kpis">' +
        '<div class="kpi hero"><strong>' + money(r.gross_cents) + "</strong><span>Gross revenue</span>" +
          (r.cents_today ? '<span class="delta">+' + money(r.cents_today) + " today</span>" : "") + "</div>" +
        '<div class="kpi click" onclick="drillAccounts(\'all\')"><strong>' + p.total_accounts + "</strong><span>Total accounts ›</span>" +
          (p.new_today ? '<span class="delta">+' + p.new_today + " today</span>" : "") + "</div>" +
        '<div class="kpi click" onclick="drillAccounts(\'creators\')"><strong>' + p.creators + "</strong><span>Creators ›</span></div>" +
        '<div class="kpi click" onclick="drillAccounts(\'watchers\')"><strong>' + p.watchers + "</strong><span>Watchers ›</span></div>" +
        '<div class="kpi"><strong>' + c.approved + "</strong><span>Films live</span></div>" +
        '<div class="kpi ' + (d.needs_action ? "warn" : "") + '"><strong>' + d.needs_action + "</strong><span>Awaiting review</span></div>" +
        '<div class="kpi"><strong>' + watchTime + "</strong><span>Watch time (Bunny)</span></div>" +
        '<div class="kpi"><strong>' + c.uploaded_today + "</strong><span>Uploaded today</span></div>" +
        "</div>";

      html +=
        '<div class="panel"><h2>👥 People</h2><div class="rows">' +
        item("Total accounts", p.total_accounts) + item("Creators", p.creators) +
        item("Watchers", p.watchers) + item("Followers (subscribers)", p.followers_total) +
        item("Paying customers", p.paying_customers) + item("Active viewers", p.active_viewers) +
        item("New today", p.new_today) + item("New this week", p.new_7d) +
        "</div></div>";

      html +=
        '<div class="panel"><h2>🎞️ Content</h2><div class="rows">' +
        item("Total films", c.total_films) + item("Live", c.approved) +
        item("Pending review", c.pending_review) + item("Awaiting upload", c.pending_upload) +
        item("Rejected", c.rejected) + item("Uploaded today", c.uploaded_today) +
        item("Uploaded this week", c.uploaded_7d) + item("Free / Paid", c.free_films + " / " + c.paid_films) +
        item("Horizontal / Vertical", c.horizontal + " / " + c.vertical) +
        item("Storage used", gb(c.storage_bytes)) +
        "</div></div>";

      html +=
        '<div class="panel"><h2>▶️ Engagement</h2><div class="rows">' +
        item("Total views", e.total_views) + item("Views today", e.views_today) +
        item("Views this week", e.views_7d) + item("Unique viewers", p.active_viewers) +
        item("Watch time", watchTime) +
        (d.stream.engagement_score !== undefined ? item("Engagement score", d.stream.engagement_score) : "") +
        "</div>" +
        (d.stream.available ? "" : '<p style="color:var(--muted);font-size:12.5px;margin-top:10px">Watch time appears once films have been streamed.</p>') +
        "</div>";

      html +=
        '<div class="panel"><h2>💰 Revenue</h2><div class="rows">' +
        item("Gross revenue", money(r.gross_cents)) + item("Today", money(r.cents_today)) +
        item("Last 30 days", money(r.cents_30d)) + item("Unlocks sold", r.unlocks_total) +
        item("Unlocks today", r.unlocks_today) + item("Active unlocks", r.active_unlocks) +
        item("Revenue per customer", money(r.arpu_cents)) + item("Conversion rate", r.conversion_pct + "%") +
        "</div></div>";

      if (d.top_films.length) {
        html += '<div class="panel"><h2>🔥 Top films</h2><table class="t"><tr><th>Film</th><th>Creator</th><th>Views</th><th>Unlocks</th><th>Price</th></tr>' +
          d.top_films.map((f) =>
            "<tr><td><a href='watch.html?film=" + f.id + "' style='color:var(--text)'>" + esc(f.title) + "</a></td>" +
            "<td style='color:var(--muted)'>@" + esc(f.handle) + "</td><td>" + f.views + "</td><td>" + f.unlocks + "</td>" +
            "<td>" + (f.price_cents == 0 ? "Free" : money(f.price_cents)) + "</td></tr>").join("") +
          "</table></div>";
      }

      if (d.signup_trend.length) {
        const max = Math.max.apply(null, d.signup_trend.map((t) => Number(t.n)));
        html += '<div class="panel"><h2>📈 Signups — last 14 days</h2><div class="chart">' +
          d.signup_trend.map((t) =>
            '<div class="bar-wrap"><div class="bar" style="height:' + (Number(t.n) / max * 90) + 'px" title="' + t.n + '"></div>' +
            '<div class="lbl">' + esc(String(t.d).slice(5)) + "</div></div>").join("") +
          "</div></div>";
      }

      el.innerHTML = html;
    }
    const item = (label, val) => '<div class="row-item"><span>' + label + "</span><b>" + val + "</b></div>";

    // ---------- Review queue (click a thumbnail to stream it) ----------
    let QUEUE = {};
    const mmss = (s) => s ? Math.floor(s / 60) + "m " + String(s % 60).padStart(2, "0") + "s" : "—";

    function thumbHtml(f) {
      const vert = f.orientation === "vertical";
      const bg = f.thumbnail_url ? "background-image:url('" + esc(f.thumbnail_url) + "')" : "";
      const encClass = f.playable ? "ok" : "busy";
      if (!f.bunny_video_id) {
        return '<div class="rev-thumb dead' + (vert ? " vert" : "") + '">' +
          '<div class="play" style="font-size:14px;color:var(--muted);text-align:center;padding:10px">⏳ Awaiting<br>upload</div></div>';
      }
      return '<div class="rev-thumb' + (vert ? " vert" : "") + (f.playable ? "" : " dead") + '" style="' + bg + '"' +
        (f.playable ? ' onclick="openPlayer(' + f.id + ')" title="Click to play"' : ' title="Still encoding"') + ">" +
        '<div class="play">' + (f.playable ? "▶" : "⏳") + "</div>" +
        '<div class="enc ' + encClass + '">' + esc(f.encode_label) + "</div></div>";
    }

    async function loadQueue() {
      const el = document.getElementById("queue");
      const d = await (await fetch(API + "/admin.php")).json();
      if (denied(el, d)) return;
      if (!d.queue.length) { el.innerHTML = '<p style="color:var(--muted)">🎉 Queue is empty — nothing awaiting review.</p>'; return; }
      QUEUE = {};
      d.queue.forEach((f) => (QUEUE[f.id] = f));
      el.innerHTML = d.queue.map((f) =>
        '<div class="queue-card" id="film-' + f.id + '"><div class="rev">' +
          thumbHtml(f) +
          '<div class="rev-body">' +
            "<h2>" + esc(f.title) + '<span class="pill wait">' + esc(String(f.status).replace("_", " ")) + "</span>" +
            '<span class="pill">' + (f.price_cents == 0 ? "FREE + ads" : money(f.price_cents)) + "</span></h2>" +
            '<p class="info">by ' + esc(f.display_name) + " (@" + esc(f.handle) + " · " + esc(f.email) + ")</p>" +
            '<p class="info">' + f.width + "×" + f.height + " · " + esc(f.orientation) + " · " + gb(f.size_bytes) +
              (f.length_seconds ? " · " + mmss(f.length_seconds) : "") + " · " + esc(f.created_at) + " UTC</p>" +
            '<p class="info">' + esc(f.synopsis) + "</p>" +
            '<div class="queue-actions">' +
              (f.playable ? '<button class="btn btn-primary" onclick="openPlayer(' + f.id + ')">▶ Watch</button>' : "") +
              '<button class="btn btn-approve" onclick="act(' + f.id + ",'approve')\">✓ Approve &amp; publish</button>" +
              '<button class="btn btn-ghost btn-reject" onclick="act(' + f.id + ",'reject')\">✕ Reject</button>" +
            "</div>" +
          "</div></div></div>"
      ).join("");
    }

    // ---------- Accounts drill-down ----------
    async function loadAccounts() {
      const el = document.getElementById("accounts");
      el.innerHTML = '<p style="color:var(--muted)">Loading accounts…</p>';
      const d = await (await fetch(API + "/admin.php?accounts=1&filter=" + encodeURIComponent(acctFilter))).json();
      if (denied(el, d)) return;

      const chip = (k, label) =>
        '<button class="btn ' + (acctFilter === k ? "btn-primary" : "btn-ghost") +
        '" onclick="drillAccounts(\'' + k + '\')">' + label + "</button>";

      let html = '<div class="tabs">' + chip("all", "All") + chip("creators", "Creators") + chip("watchers", "Watchers") + "</div>";

      if (!d.accounts.length) {
        el.innerHTML = html + '<p style="color:var(--muted)">No accounts in this view yet.</p>';
        return;
      }

      html += d.accounts.map((a) => {
        a.films.forEach((f) => (QUEUE[f.id] = f));
        const initials = (a.display_name || a.handle || "?").slice(0, 2).toUpperCase();
        const av = a.avatar_url
          ? '<img src="' + esc(a.avatar_url) + '" style="width:46px;height:46px;border-radius:50%;object-fit:cover" alt="" />'
          : '<span class="avatar g3" style="width:46px;height:46px">' + esc(initials) + "</span>";

        const filmRows = a.films.length
          ? a.films.map((f) =>
              '<div class="acct-film" id="film-' + f.id + '">' +
                '<div style="flex:1;min-width:180px">' +
                  "<b>" + esc(f.title) + "</b>" +
                  '<span class="pill ' + (f.status === "approved" ? "live" : "wait") + '">' + esc(String(f.status).replace("_", " ")) + "</span>" +
                  '<span class="pill">' + (f.price_cents == 0 ? "FREE" : money(f.price_cents)) + "</span>" +
                  '<div style="color:var(--muted);font-size:12px;margin-top:3px">' + f.width + "×" + f.height +
                    " · " + esc(f.orientation) + " · " + f.views + " views · " + esc(f.encode_label) + "</div>" +
                "</div>" +
                '<div class="queue-actions">' +
                  (f.playable ? '<button class="btn btn-ghost" onclick="openPlayer(' + f.id + ')">▶</button>' : "") +
                  (f.status !== "approved" ? '<button class="btn btn-approve" onclick="act(' + f.id + ",'approve')\">✓ Accept</button>" : "") +
                  (f.status !== "rejected" ? '<button class="btn btn-ghost btn-reject" onclick="act(' + f.id + ",'reject')\">✕ Reject</button>" : "") +
                "</div>" +
              "</div>").join("")
          : '<p style="color:var(--muted);font-size:13px;margin:0">No films uploaded.</p>';

        return '<div class="queue-card" id="acct-' + esc(a.id) + '">' +
          '<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-bottom:14px">' + av +
            '<div style="flex:1;min-width:200px">' +
              "<b style='font-size:16px'>" + esc(a.display_name || "—") + "</b>" +
              '<span class="pill ' + (Number(a.is_creator) === 1 ? "live" : "") + '">' +
                (Number(a.is_creator) === 1 ? "CREATOR" : "WATCHER") + "</span>" +
              (Number(a.suspended) === 1 ? '<span class="pill wait">SUSPENDED</span>' : "") +
              '<div style="color:var(--muted);font-size:12.5px;margin-top:3px">@' + esc(a.handle) + " · " + esc(a.email) +
                " · joined " + esc(String(a.created_at).slice(0, 10)) + "</div>" +
            "</div></div>" +

          '<div class="rows" style="margin-bottom:14px">' +
            item("Films", a.films_used + " / " + a.film_slots + " slots") +
            item("Followers", a.followers) + item("Watched", a.watched) +
            item("Purchases", a.purchases) + item("Spent", money(a.spent_cents)) +
          "</div>" +

          '<div class="acct-ctl">' +
            '<label>Film slots</label>' +
            '<input type="number" min="1" max="100" value="' + a.film_slots + '" id="slots-' + esc(a.id) + '" />' +
            '<button class="btn btn-primary" onclick="setSlots(\'' + esc(a.id) + '\')">Save</button>' +
            '<button class="btn btn-ghost" onclick="setCreator(\'' + esc(a.id) + "'," + (Number(a.is_creator) === 1 ? "false" : "true") + ')">' +
              (Number(a.is_creator) === 1 ? "Remove creator" : "Make creator") + "</button>" +
            '<button class="btn btn-ghost ' + (Number(a.suspended) === 1 ? "" : "btn-reject") + '" onclick="setSuspended(\'' + esc(a.id) + "'," + (Number(a.suspended) === 1 ? "false" : "true") + ')">' +
              (Number(a.suspended) === 1 ? "Unsuspend" : "Suspend") + "</button>" +
          "</div>" +
          '<div style="margin-top:14px">' + filmRows + "</div>" +
        "</div>";
      }).join("");

      el.innerHTML = html;
    }

    async function adminPost(body, okMsg) {
      const d = await (await fetch(API + "/admin.php", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      })).json();
      if (!d.ok) {
        alert(d.error === "below_current_usage"
          ? "Can't set slots below the " + d.used + " film(s) this account already has."
          : "Failed: " + (d.error || "unknown"));
        return false;
      }
      loaded.overview = false; loaded.accounts = false; loaded.videos = false;
      if (okMsg) showTab("accounts");
      return true;
    }
    const setSlots = (id) =>
      adminPost({ action: "set_slots", user_id: id, film_slots: parseInt(document.getElementById("slots-" + id).value, 10) }, true);
    const setCreator = (id, v) => adminPost({ action: "set_creator", user_id: id, is_creator: v }, true);
    const setSuspended = (id, v) => {
      if (v && !confirm("Suspend this account? They won't be able to submit films.")) return;
      return adminPost({ action: "set_suspended", user_id: id, suspended: v }, true);
    };

    // ---------- Streaming review window ----------
    const back = document.getElementById("player-back");
    function openPlayer(id) {
      const f = QUEUE[id];
      if (!f || !f.embed_url) return;
      const vert = f.orientation === "vertical";
      document.getElementById("player-box").className = "player-box" + (vert ? " vert" : "");
      document.getElementById("pl-title").textContent = f.title;
      document.getElementById("pl-frame").innerHTML =
        '<iframe src="' + f.embed_url + '" loading="lazy" allowfullscreen ' +
        'allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture"></iframe>';
      document.getElementById("pl-meta").innerHTML =
        "by " + esc(f.display_name) + " (@" + esc(f.handle) + ") · " + f.width + "×" + f.height +
        " · " + esc(f.orientation) + " · " + gb(f.size_bytes) +
        (f.length_seconds ? " · " + mmss(f.length_seconds) : "") +
        " · " + (f.price_cents == 0 ? "FREE + ads" : money(f.price_cents) + " · 7-day unlock") +
        "<br>" + esc(f.synopsis);
      document.getElementById("pl-actions").innerHTML =
        '<button class="btn btn-approve" onclick="act(' + f.id + ",'approve')\">✓ Approve &amp; publish</button>" +
        '<button class="btn btn-ghost btn-reject" onclick="act(' + f.id + ",'reject')\">✕ Reject</button>" +
        '<button class="btn btn-ghost" onclick="closePlayer()">Close</button>';
      back.classList.add("open");
      document.body.style.overflow = "hidden";
    }
    function closePlayer() {
      back.classList.remove("open");
      document.getElementById("pl-frame").innerHTML = ""; // stop playback
      document.body.style.overflow = "";
    }
    document.addEventListener("keydown", (e) => { if (e.key === "Escape") closePlayer(); });

    async function act(id, action) {
      if (action === "reject" && !confirm("Reject this film? The creator will see it marked rejected.")) return;
      const d = await (await fetch(API + "/admin.php", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ film_id: id, action }),
      })).json();
      if (!d.ok) { alert("Action failed: " + (d.error || "unknown")); return; }
      closePlayer();
      const card = document.getElementById("film-" + id);
      if (card) card.remove();
      loaded.overview = false; loaded.videos = false;
      const q = document.getElementById("queue");
      if (q && !q.querySelector(".queue-card")) {
        q.innerHTML = '<p style="color:var(--muted)">🎉 Queue is empty — nothing awaiting review.</p>';
      }
    }

    // ---------- All videos ----------
    async function loadVideos() {
      const el = document.getElementById("videos");
      const d = await (await fetch(API + "/admin.php?all=1")).json();
      if (denied(el, d)) return;
      if (!d.videos.length) { el.innerHTML = '<p style="color:var(--muted)">No videos on the platform yet.</p>'; return; }
      d.videos.forEach((f) => (QUEUE[f.id] = f));
      el.innerHTML = d.videos.map((f) =>
        '<div class="queue-card" id="film-' + f.id + '"><div class="rev">' +
          thumbHtml(f) +
          '<div class="rev-body">' +
            "<h2>#" + f.id + " · " + esc(f.title) +
            '<span class="pill ' + (f.status === "approved" ? "live" : "wait") + '">' + esc(String(f.status).replace("_", " ")) + "</span>" +
            '<span class="pill">' + (f.price_cents == 0 ? "FREE" : money(f.price_cents)) + "</span>" +
            '<span class="pill">' + esc(f.orientation) + "</span></h2>" +
            '<p class="info">by ' + esc(f.display_name) + " (@" + esc(f.handle) + " · " + esc(f.email) + ")</p>" +
            '<p class="info">' + f.width + "×" + f.height + " · " + gb(f.size_bytes) +
              (f.length_seconds ? " · " + mmss(f.length_seconds) : "") + " · " + f.views + " views · " + esc(f.created_at) + " UTC</p>" +
            '<p class="info">📦 ' + esc(f.storage) + "</p>" +
            '<div class="queue-actions">' +
              (f.playable ? '<button class="btn btn-primary" onclick="openPlayer(' + f.id + ')">▶ Watch</button>' : "") +
              (f.status === "approved" ? '<a class="btn btn-ghost" href="watch.html?film=' + f.id + '">Public page</a>' : "") +
              (["pending_review", "pending_upload"].indexOf(f.status) >= 0
                ? '<button class="btn btn-approve" onclick="act(' + f.id + ",'approve')\">✓ Approve</button>" : "") +
              (f.bunny_video_id ? '<a class="btn btn-ghost" href="https://dash.bunny.net/stream/' + esc(d.library_id) + '/library/videos" target="_blank" rel="noopener">Bunny ↗</a>' : "") +
            "</div>" +
          "</div></div></div>"
      ).join("");
    }

    loadOverview(); loaded.overview = true;
  </script>
</body>
</html>
