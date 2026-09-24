// AI Flixs — motion layer: neural-network canvas, decode text, scroll
// reveals, parallax, scroll progress, marquee, live timecode.
// Everything degrades gracefully: no JS = fully visible static page,
// prefers-reduced-motion = static frame, no animation.
(function () {
  const reduce = !!(window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches);
  const lite = document.body && document.body.dataset.fx === "lite"; // admin: progress bar only
  document.documentElement.classList.add("fx");

  // ---------- Scroll progress + nav state + parallax ----------
  const bar = document.createElement("div");
  bar.className = "scroll-progress";
  bar.setAttribute("aria-hidden", "true");
  document.body.appendChild(bar);

  const layers = Array.from(document.querySelectorAll("[data-parallax]"));
  let ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const h = document.documentElement;
      const y = h.scrollTop || document.body.scrollTop;
      const max = h.scrollHeight - h.clientHeight;
      bar.style.transform = "scaleX(" + (max > 0 ? y / max : 0).toFixed(4) + ")";
      document.body.classList.toggle("scrolled", y > 12);
      if (!reduce) {
        for (const el of layers) {
          const k = parseFloat(el.dataset.parallax) || 0.2;
          el.style.translate = "0 " + (y * k).toFixed(1) + "px";
        }
      }
      ticking = false;
    });
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  // ---------- Scroll reveal ----------
  const AUTO = [
    ".section-head", ".film-card", ".feature", ".panel", ".about-card", ".card", ".rule",
    ".tile", ".creator-callout", ".perks", ".role", ".unlock-row", ".catalogue-empty",
    ".marquee", ".profile-stats", ".member-stats",
  ].join(",");
  const io = !reduce && !lite && "IntersectionObserver" in window
    ? new IntersectionObserver(onReveal, { threshold: 0.12, rootMargin: "0px 0px -6% 0px" })
    : null;

  function onReveal(entries) {
    let i = 0;
    for (const e of entries) {
      if (!e.isIntersecting) continue;
      const el = e.target;
      const delay = Math.min(i++ * 80, 480);
      el.style.transitionDelay = delay + "ms";
      el.classList.add("in");
      io.unobserve(el);
      // Hand the element back to its normal styles (keeps hover effects snappy)
      setTimeout(() => { el.removeAttribute("data-reveal"); el.classList.remove("in"); el.style.transitionDelay = ""; }, 900 + delay);
    }
  }
  function tag(root) {
    if (!io) return;
    const els = root.matches && root.matches(AUTO) ? [root] : [];
    if (root.querySelectorAll) els.push(...root.querySelectorAll(AUTO));
    for (const el of els) {
      if (el.dataset.revealDone) continue;
      el.dataset.revealDone = "1";
      // Nested blocks ride along with their parent — no double blur
      if (el.parentElement && el.parentElement.closest("[data-reveal]")) continue;
      el.setAttribute("data-reveal", "");
      io.observe(el);
    }
  }
  tag(document.body);
  if (io && "MutationObserver" in window) {
    // Film grids, dashboards and profiles render after fetch() — reveal those too
    new MutationObserver((muts) => {
      for (const m of muts) for (const n of m.addedNodes) if (n.nodeType === 1) tag(n);
    }).observe(document.body, { childList: true, subtree: true });
  }

  // ---------- Marquee: duplicate content for a seamless loop ----------
  document.querySelectorAll(".marquee-track").forEach((t) => {
    t.innerHTML += t.innerHTML;
    t.querySelectorAll("span").forEach((s, i) => { if (i >= t.children.length / 2) s.setAttribute("aria-hidden", "true"); });
  });

  // ---------- Decode / scramble text ----------
  // Glyphs are matched to each letter's case so the scrambled line keeps
  // roughly the same width and never re-wraps mid-animation.
  const LOWER = "abcdeghknopqrsuvxyz", UPPER = "ABCDEGHKNOPQRSUVXYZ", DIGIT = "0123456789";
  const pick = (set) => set[(Math.random() * set.length) | 0];
  document.querySelectorAll("[data-scramble]").forEach((el) => {
    if (reduce) return;
    const final = el.textContent;
    const box = el.getBoundingClientRect();
    el.style.minHeight = box.height + "px"; // hold vertical space
    let frame = 0;
    const total = final.length * 3 + 18;
    el.setAttribute("aria-label", final);
    function tick() {
      let out = "";
      for (let i = 0; i < final.length; i++) {
        const ch = final[i];
        if (!/[A-Za-z0-9]/.test(ch) || frame > i * 3 + 18) out += ch;
        else out += pick(/[a-z]/.test(ch) ? LOWER : /[A-Z]/.test(ch) ? UPPER : DIGIT);
      }
      el.textContent = out;
      if (++frame <= total) setTimeout(tick, 38);
      else el.textContent = final;
    }
    setTimeout(tick, 350);
  });

  // ---------- Live timecode (HH:MM:SS:FF @ 24fps) ----------
  document.querySelectorAll("[data-timecode]").forEach((el) => {
    const t0 = performance.now();
    const pad = (n) => String(n).padStart(2, "0");
    function draw() {
      const f = Math.floor(((performance.now() - t0) / 1000) * 24);
      const s = Math.floor(f / 24);
      el.textContent = pad(Math.floor(s / 3600)) + ":" + pad(Math.floor(s / 60) % 60) + ":" + pad(s % 60) + ":" + pad(f % 24);
    }
    draw();
    if (!reduce) setInterval(draw, 1000 / 24);
  });

  // ---------- Neural-network canvas ----------
  const cv = document.getElementById("neural-canvas");
  if (cv && cv.getContext) neural(cv);

  function neural(cv) {
    const ctx = cv.getContext("2d");
    const BLUE = "47,107,255", CYAN = "34,184,255", RED = "255,45,70";
    const LINK = 140, LINK2 = LINK * LINK;
    let W = 0, H = 0, nodes = [], pulses = [], links = [];
    let raf = 0, onScreen = true, mouse = { x: -1e4, y: -1e4 };

    function size() {
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const r = cv.getBoundingClientRect();
      W = r.width; H = r.height;
      cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      const count = Math.round(Math.min(95, Math.max(34, (W * H) / 15000)));
      nodes = Array.from({ length: count }, () => {
        const roll = Math.random();
        return {
          x: Math.random() * W, y: Math.random() * H,
          vx: (Math.random() - 0.5) * 0.28, vy: (Math.random() - 0.5) * 0.28,
          r: Math.random() * 1.7 + 0.8,
          c: roll < 0.16 ? RED : roll < 0.58 ? BLUE : CYAN,
          ph: Math.random() * Math.PI * 2,
        };
      });
      pulses = [];
    }

    function step(t) {
      ctx.clearRect(0, 0, W, H);

      for (const n of nodes) {
        n.x += n.vx; n.y += n.vy;
        if (n.x < -30) n.x = W + 30; else if (n.x > W + 30) n.x = -30;
        if (n.y < -30) n.y = H + 30; else if (n.y > H + 30) n.y = -30;
        const dx = mouse.x - n.x, dy = mouse.y - n.y, d2 = dx * dx + dy * dy;
        if (d2 < 30000) { n.x += dx * 0.004; n.y += dy * 0.004; }
      }

      // synapses
      links = [];
      ctx.lineWidth = 1;
      for (let i = 0; i < nodes.length; i++) {
        const a = nodes[i];
        for (let j = i + 1; j < nodes.length; j++) {
          const b = nodes[j];
          const dx = a.x - b.x, dy = a.y - b.y, d2 = dx * dx + dy * dy;
          if (d2 > LINK2) continue;
          const alpha = (1 - d2 / LINK2) * 0.42;
          const hot = a.c === RED || b.c === RED;
          ctx.strokeStyle = "rgba(" + (hot ? RED : BLUE) + "," + (hot ? alpha * 0.7 : alpha).toFixed(3) + ")";
          ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
          links.push([a, b]);
        }
      }

      // mouse links
      for (const n of nodes) {
        const dx = mouse.x - n.x, dy = mouse.y - n.y, d2 = dx * dx + dy * dy;
        if (d2 < 26000) {
          ctx.strokeStyle = "rgba(" + CYAN + "," + ((1 - d2 / 26000) * 0.55).toFixed(3) + ")";
          ctx.beginPath(); ctx.moveTo(n.x, n.y); ctx.lineTo(mouse.x, mouse.y); ctx.stroke();
        }
      }

      // signal pulses travelling along synapses
      if (links.length && pulses.length < 26 && Math.random() < 0.22) {
        const [a, b] = links[(Math.random() * links.length) | 0];
        pulses.push({ a, b, t: 0, v: 0.012 + Math.random() * 0.02, c: Math.random() < 0.35 ? RED : CYAN });
      }
      pulses = pulses.filter((p) => {
        p.t += p.v;
        if (p.t >= 1) return false;
        const x = p.a.x + (p.b.x - p.a.x) * p.t, y = p.a.y + (p.b.y - p.a.y) * p.t;
        ctx.fillStyle = "rgba(" + p.c + ",.16)";
        ctx.beginPath(); ctx.arc(x, y, 6, 0, 6.283); ctx.fill();
        ctx.fillStyle = "rgba(" + p.c + ",.95)";
        ctx.beginPath(); ctx.arc(x, y, 1.9, 0, 6.283); ctx.fill();
        return true;
      });

      // neurons (breathing glow)
      for (const n of nodes) {
        const g = 0.55 + 0.45 * Math.sin(t / 900 + n.ph);
        ctx.fillStyle = "rgba(" + n.c + "," + (0.1 * g).toFixed(3) + ")";
        ctx.beginPath(); ctx.arc(n.x, n.y, n.r * 4.5, 0, 6.283); ctx.fill();
        ctx.fillStyle = "rgba(" + n.c + "," + (0.55 + 0.4 * g).toFixed(3) + ")";
        ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, 6.283); ctx.fill();
      }
    }

    function loop(t) {
      step(t);
      raf = onScreen && !document.hidden ? requestAnimationFrame(loop) : 0;
    }
    function start() { if (!raf && !reduce) raf = requestAnimationFrame(loop); }

    size();
    if (reduce) step(0); else start();

    let rt;
    window.addEventListener("resize", () => { clearTimeout(rt); rt = setTimeout(() => { size(); if (reduce) step(0); }, 150); });
    const host = cv.parentElement;
    host.addEventListener("pointermove", (e) => {
      const r = cv.getBoundingClientRect();
      mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top;
    });
    host.addEventListener("pointerleave", () => { mouse.x = mouse.y = -1e4; });
    document.addEventListener("visibilitychange", () => { if (!document.hidden) start(); });
    if ("IntersectionObserver" in window) {
      new IntersectionObserver((es) => { onScreen = es[0].isIntersecting; if (onScreen) start(); }).observe(cv);
    }
  }
})();
