// AI Flixs — submission page: client-side validation of upload rules
(function () {
  const cfg = window.AF_CONFIG;
  const drop = document.getElementById("dropzone");
  const input = document.getElementById("file-input");
  const status = document.getElementById("file-status");
  const submitBtn = document.getElementById("submit-btn");
  const progress = document.getElementById("progress");
  // The submission form is embedded in both upload.html and the Creator
  // Studio — bail out quietly on pages that don't include it.
  if (!drop || !input || !progress || !submitBtn) return;
  const bar = progress.querySelector(".bar");
  let validFile = null;

  const gb = (b) => (b / 1024 ** 3).toFixed(2) + " GB";

  // Creator-set pricing: show the price box only for pay-per-view
  const pricingMode = document.getElementById("pricing-mode");
  const priceField = document.getElementById("price-field");
  const priceInput = document.getElementById("price");
  pricingMode.addEventListener("change", () => {
    priceField.style.display = pricingMode.value === "paid" ? "block" : "none";
  });

  function getPriceCents() {
    if (pricingMode.value === "free") return 0;
    const p = Math.round(parseFloat(priceInput.value) * 100);
    if (isNaN(p) || p < cfg.PRICE_MIN_CENTS || p > cfg.PRICE_MAX_CENTS) return null;
    return p;
  }

  function setStatus(msg, ok) {
    status.textContent = msg;
    status.className = "file-status " + (ok ? "ok" : "err");
  }

  function checkFile(file) {
    validFile = null;
    submitBtn.disabled = true;

    if (!file.type.startsWith("video/")) {
      return setStatus("That's not a video file. Upload MP4, MOV or WebM.", false);
    }
    if (file.size > cfg.MAX_BYTES) {
      return setStatus(
        `File is ${gb(file.size)} — the limit is ${gb(cfg.MAX_BYTES)}.`, false
      );
    }

    // Read resolution from video metadata without uploading anything
    const url = URL.createObjectURL(file);
    const v = document.createElement("video");
    v.preload = "metadata";
    v.onloadedmetadata = () => {
      URL.revokeObjectURL(url);
      const shortSide = Math.min(v.videoWidth, v.videoHeight);
      if (!shortSide) {
        return setStatus("Couldn't read the video's resolution. Try MP4 (H.264).", false);
      }
      if (shortSide < cfg.MIN_RES) {
        return setStatus(
          `Resolution is ${v.videoWidth}×${v.videoHeight} — minimum is ${cfg.MIN_RES}p.`, false
        );
      }
      if (shortSide > cfg.MAX_RES) {
        return setStatus(
          `Resolution is ${v.videoWidth}×${v.videoHeight} — maximum is ${cfg.MAX_RES}p for now.`, false
        );
      }
      validFile = file;
      submitBtn.disabled = false;
      setStatus(
        `✓ ${file.name} — ${gb(file.size)}, ${v.videoWidth}×${v.videoHeight}. Ready to submit.`, true
      );
    };
    v.onerror = () => {
      URL.revokeObjectURL(url);
      setStatus("Couldn't read that file as video. Try MP4 (H.264).", false);
    };
    v.src = url;
  }

  // Real video ingest: resumable TUS upload straight from the browser to
  // Bunny Stream (activates once BUNNY_STREAM_* is configured server-side).
  async function startVideoUpload() {
    const r = await fetch(cfg.API_BASE + "/upload_begin.php", { method: "POST" });
    const d = await r.json();
    if (d.error === "stream_not_configured") {
      setStatus("✓ Film registered! Video ingest goes live once Bunny Stream is connected.", true);
      submitBtn.textContent = "Submitted";
      return;
    }
    if (!d.videoId) {
      setStatus("Upload setup failed: " + (d.error || "unknown"), false);
      submitBtn.disabled = false;
      return;
    }

    if (!window.tus) {
      await new Promise((res, rej) => {
        const s = document.createElement("script");
        s.src = "https://cdn.jsdelivr.net/npm/tus-js-client@4/dist/tus.min.js";
        s.onload = res; s.onerror = rej;
        document.head.appendChild(s);
      });
    }

    progress.style.display = "block";
    const up = new tus.Upload(validFile, {
      endpoint: d.endpoint,
      retryDelays: [0, 3000, 10000, 30000, 60000],
      chunkSize: 50 * 1024 * 1024,
      headers: {
        AuthorizationSignature: d.signature,
        AuthorizationExpire: d.expiration,
        VideoId: d.videoId,
        LibraryId: d.libraryId,
      },
      metadata: { filetype: validFile.type, title: document.getElementById("title").value },
      onProgress: (sent, total) => {
        const pct = ((sent / total) * 100).toFixed(1);
        bar.style.width = pct + "%";
        setStatus("Uploading… " + pct + "% — keep this tab open.", true);
      },
      onSuccess: async () => {
        await fetch(cfg.API_BASE + "/upload_complete.php", { method: "POST" });
        bar.style.width = "100%";
        setStatus("✓ Upload complete! Your film is queued for review.", true);
        submitBtn.textContent = "Submitted";
      },
      onError: (err) => {
        setStatus("Upload failed: " + err, false);
        submitBtn.disabled = false;
      },
    });
    up.start();
  }

  drop.addEventListener("click", () => input.click());
  input.addEventListener("change", () => input.files[0] && checkFile(input.files[0]));
  ["dragover", "dragenter"].forEach((e) =>
    drop.addEventListener(e, (ev) => { ev.preventDefault(); drop.classList.add("drag"); })
  );
  ["dragleave", "drop"].forEach((e) =>
    drop.addEventListener(e, (ev) => { ev.preventDefault(); drop.classList.remove("drag"); })
  );
  drop.addEventListener("drop", (ev) => {
    const f = ev.dataTransfer.files[0];
    if (f) checkFile(f);
  });

  document.getElementById("upload-form").addEventListener("submit", async (ev) => {
    ev.preventDefault();
    if (!validFile) return;

    if (window.AF.live) {
      if (!window.AF.user) {
        setStatus("Please sign in first — your film needs a creator page.", false);
        return;
      }
      const priceCents = getPriceCents();
      if (priceCents === null) {
        setStatus("Enter a price between $2.99 and $100 (or choose Free).", false);
        return;
      }
      submitBtn.disabled = true;
      const v = document.createElement("video");
      v.preload = "metadata";
      const url = URL.createObjectURL(validFile);
      v.onloadedmetadata = async () => {
        URL.revokeObjectURL(url);
        const r = await fetch(cfg.API_BASE + "/submit.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            title: document.getElementById("title").value,
            synopsis: document.getElementById("desc").value,
            price_cents: priceCents,
            width: v.videoWidth,
            height: v.videoHeight,
            size_bytes: validFile.size,
          }),
        });
        const d = await r.json();
        if (d.ok) {
          startVideoUpload();
        } else if (d.error === "slot_limit") {
          setStatus("You've used all " + d.slots + " of your film slots. Ask an admin to raise your limit.", false);
          submitBtn.disabled = false;
        } else if (d.error === "account_suspended") {
          setStatus("This account is suspended and can't submit films.", false);
        } else {
          setStatus("Submission failed: " + (d.error || "unknown error"), false);
          submitBtn.disabled = false;
        }
      };
      v.src = url;
      return;
    }

    {
      // Demo: simulate upload progress. Production uses TUS resumable upload
      // direct to the video CDN (Bunny Stream / Cloudflare Stream) — see README.
      progress.style.display = "block";
      submitBtn.disabled = true;
      let pct = 0;
      const t = setInterval(() => {
        pct = Math.min(pct + Math.random() * 18, 100);
        bar.style.width = pct + "%";
        if (pct >= 100) {
          clearInterval(t);
          setStatus("✓ Demo submission complete. Connect the backend to accept real uploads.", true);
          submitBtn.textContent = "Submitted";
        }
      }, 300);
      return;
    }
  });
})();
