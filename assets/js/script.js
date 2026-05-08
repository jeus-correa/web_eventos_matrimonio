/**
 * Frontend: subida AJAX con progreso, galería polling, masonry, modal, likes, comentarios,
 * cuenta regresiva, música, slideshow/TV, compartir y PWA (service worker).
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", () => {
    initReducedMotionClass();
    if (document.body.classList.contains("page-home")) initHome();
    if (document.body.classList.contains("page-event")) initEventPage();
    registerServiceWorker();
  });

  function initReducedMotionClass() {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      document.documentElement.classList.add("reduce-motion");
    }
  }

  function initHome() {
    const reveals = document.querySelectorAll(".reveal");
    if (!("IntersectionObserver" in window)) {
      reveals.forEach((el) => el.classList.add("revealed"));
      return;
    }
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((en) => {
          if (en.isIntersecting) {
            en.target.classList.add("revealed");
            io.unobserve(en.target);
          }
        });
      },
      { threshold: 0.12 }
    );
    reveals.forEach((el) => io.observe(el));
  }

  function registerServiceWorker() {
    if (!("serviceWorker" in navigator)) return;
    navigator.serviceWorker.register("sw.js").catch(() => {});
  }

  function initEventPage() {
    const body = document.body;
    const eventId = parseInt(body.dataset.eventId || "0", 10);
    const token = body.dataset.uploadToken || "";
    const pollMs = parseInt(body.dataset.pollMs || "8000", 10);
    const shareUrl = body.dataset.shareUrl || window.location.href;

    initScrollButtons();
    initCountdown();
    initMusic();
    initUpload(eventId, token);
    initCommentModal(eventId, token);
    const gallery = initGallery(eventId, token, pollMs, shareUrl);
    initLightbox();
    initShare(shareUrl);
    initSlideshowTv(gallery);
    maybeAskNotify();
  }

  function initScrollButtons() {
    document.querySelectorAll("[data-scroll]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const sel = btn.getAttribute("data-scroll");
        const el = sel ? document.querySelector(sel) : null;
        if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    });
  }

  function initCountdown() {
    const el = document.getElementById("countdown");
    if (!el) return;
    const target = el.dataset.target;
    if (!target) return;
    const end = new Date(target).getTime();
    const units = {
      days: el.querySelector('[data-u="days"]'),
      hours: el.querySelector('[data-u="hours"]'),
      minutes: el.querySelector('[data-u="minutes"]'),
      seconds: el.querySelector('[data-u="seconds"]'),
    };

    function tick() {
      const now = Date.now();
      let diff = Math.max(0, end - now);
      const s = Math.floor(diff / 1000);
      const d = Math.floor(s / 86400);
      const h = Math.floor((s % 86400) / 3600);
      const m = Math.floor((s % 3600) / 60);
      const sec = s % 60;
      if (units.days) units.days.textContent = String(d).padStart(2, "0");
      if (units.hours) units.hours.textContent = String(h).padStart(2, "0");
      if (units.minutes) units.minutes.textContent = String(m).padStart(2, "0");
      if (units.seconds) units.seconds.textContent = String(sec).padStart(2, "0");
      if (diff <= 0) return;
      requestAnimationFrame(() => setTimeout(tick, 1000));
    }
    tick();
  }

  function initMusic() {
    const btn = document.getElementById("btnMusic");
    const wrap = document.getElementById("ytWrap");
    if (btn && wrap && btn.dataset.youtube) {
      btn.addEventListener("click", () => {
        const open = wrap.classList.toggle("hidden");
        btn.setAttribute("aria-pressed", open ? "false" : "true");
        if (!open && !wrap.querySelector("iframe")) {
          const id = btn.dataset.youtube;
          const iframe = document.createElement("iframe");
          iframe.src = "https://www.youtube-nocookie.com/embed/" + id + "?autoplay=1&rel=0";
          iframe.allow = "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";
          iframe.loading = "lazy";
          wrap.appendChild(iframe);
        }
      });
    }
    const audio = document.getElementById("bgAudio");
    const btnA = document.getElementById("btnAudioToggle");
    if (audio && btnA) {
      btnA.addEventListener("click", () => {
        if (audio.paused) {
          audio.play().catch(() => {});
          btnA.setAttribute("aria-pressed", "true");
        } else {
          audio.pause();
          btnA.setAttribute("aria-pressed", "false");
        }
      });
    }
  }

  function initUpload(eventId, token) {
    const form = document.getElementById("uploadForm");
    if (!form) return;
    const input = document.getElementById("fileInput");
    const drop = document.getElementById("dropzone");
    const pick = document.getElementById("pickFile");
    const msg = document.getElementById("uploadMsg");
    const progressWrap = document.getElementById("progressWrap");
    const progressFill = document.getElementById("progressFill");
    const progressText = document.getElementById("progressText");
    const btnCam = document.getElementById("btnCamera");
    const preview = document.getElementById("cameraPreview");
    const canvas = document.getElementById("cameraCanvas");
    let stream = null;
    let capturedBlob = null;
    let cameraLive = false;

    function showMsg(text, ok) {
      if (!msg) return;
      msg.textContent = text;
      msg.classList.remove("hidden", "form-error");
      if (!ok) msg.classList.add("form-error");
    }

    pick?.addEventListener("click", () => input?.click());
    input?.addEventListener("change", () => {
      capturedBlob = null;
    });

    drop?.addEventListener("dragover", (e) => {
      e.preventDefault();
      drop.classList.add("dragover");
    });
    drop?.addEventListener("dragleave", () => drop.classList.remove("dragover"));
    drop?.addEventListener("drop", (e) => {
      e.preventDefault();
      drop.classList.remove("dragover");
      if (e.dataTransfer?.files?.length) {
        input.files = e.dataTransfer.files;
        capturedBlob = null;
      }
    });

    btnCam?.addEventListener("click", async () => {
      if (!navigator.mediaDevices?.getUserMedia) {
        showMsg("Tu navegador no permite abrir la cámara desde aquí.", false);
        return;
      }
      try {
        if (cameraLive) {
          if (!preview || !canvas) return;
          canvas.width = preview.videoWidth;
          canvas.height = preview.videoHeight;
          const ctx = canvas.getContext("2d");
          ctx.drawImage(preview, 0, 0);
          capturedBlob = await new Promise((res) => canvas.toBlob(res, "image/jpeg", 0.88));
          stream?.getTracks().forEach((t) => t.stop());
          stream = null;
          cameraLive = false;
          preview.classList.add("hidden");
          preview.srcObject = null;
          btnCam.textContent = "Usar cámara";
          showMsg("Foto lista. Completa tu nombre y envía.", true);
          return;
        }

        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
        cameraLive = true;
        if (preview) {
          preview.classList.remove("hidden");
          preview.srcObject = stream;
          await preview.play();
        }
        btnCam.textContent = "Capturar foto";
      } catch {
        showMsg("No se pudo acceder a la cámara. Revisa permisos.", false);
      }
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      if (capturedBlob) {
        fd.set("file", capturedBlob, "camara-" + Date.now() + ".jpg");
      }
      if (!fd.get("file") || (fd.get("file") instanceof File && fd.get("file").size === 0)) {
        showMsg("Selecciona un archivo o usa la cámara.", false);
        return;
      }

      const xhr = new XMLHttpRequest();
      xhr.open("POST", "upload.php");
      if (progressWrap) progressWrap.classList.remove("hidden");
      if (progressFill) progressFill.style.width = "0%";
      xhr.upload.addEventListener("progress", (ev) => {
        if (!ev.lengthComputable) return;
        const p = Math.round((ev.loaded / ev.total) * 100);
        if (progressFill) progressFill.style.width = p + "%";
        if (progressText) progressText.textContent = p + "%";
      });
      xhr.onload = () => {
        if (progressWrap) progressWrap.classList.add("hidden");
        try {
          const res = JSON.parse(xhr.responseText || "{}");
          if (res.ok) {
            showMsg("¡Listo! Tu recuerdo ya está en la galería.", true);
            form.reset();
            capturedBlob = null;
            window.dispatchEvent(new CustomEvent("gallery:new", { detail: res.item }));
          } else {
            showMsg(res.error || "No se pudo subir.", false);
          }
        } catch {
          showMsg("Error de respuesta del servidor.", false);
        }
      };
      xhr.onerror = () => {
        if (progressWrap) progressWrap.classList.add("hidden");
        showMsg("Error de red.", false);
      };
      xhr.send(fd);
    });
  }

  function initGallery(eventId, token, pollMs, shareUrl) {
    const root = document.getElementById("masonry");
    const empty = document.getElementById("galleryEmpty");
    if (!root) return null;

    const state = { lastId: 0, byId: new Map() };

    function renderItem(it) {
      const card = document.createElement("div");
      card.className = "masonry-item";
      card.dataset.id = String(it.id);
      const media =
        it.kind === "video"
          ? (() => {
              const v = document.createElement("video");
              v.src = it.url;
              v.muted = true;
              v.playsInline = true;
              v.loop = true;
              v.preload = "metadata";
              return v;
            })()
          : (() => {
              const im = document.createElement("img");
              im.src = it.url;
              im.alt = "Recuerdo de " + it.guest_name;
              im.loading = "lazy";
              return im;
            })();

      const meta = document.createElement("div");
      meta.className = "masonry-meta";
      const who = document.createElement("span");
      who.textContent = it.guest_name;
      const actions = document.createElement("div");
      actions.className = "masonry-actions";

      const like = document.createElement("button");
      like.type = "button";
      like.className = "icon-btn";
      like.textContent = "❤ " + (it.likes || 0);
      like.addEventListener("click", () => toggleLike(it.id, like));

      const open = document.createElement("button");
      open.type = "button";
      open.className = "icon-btn";
      open.textContent = "Ver";
      open.addEventListener("click", () => openLightbox(it));

      const dl = document.createElement("a");
      dl.className = "icon-btn";
      dl.href = it.url;
      dl.download = "";
      dl.textContent = "↓";

      const cm = document.createElement("button");
      cm.type = "button";
      cm.className = "icon-btn";
      cm.textContent = "💬";
      cm.addEventListener("click", () => openCommentModal(it.id));

      actions.append(like, open, dl, cm);
      meta.append(who, actions);
      card.append(media, meta);
      root.prepend(card);
      state.byId.set(it.id, { el: card, data: it, likeBtn: like });
    }

    function mergeItems(items) {
      let max = state.lastId;
      items.forEach((it) => {
        if (!state.byId.has(it.id)) {
          renderItem(it);
          notifyNew(it);
          if (it.id > max) max = it.id;
        }
      });
      state.lastId = Math.max(state.lastId, max);
      if (empty) empty.classList.toggle("hidden", state.byId.size > 0);
    }

    async function fetchGallery(since) {
      const u = new URL("gallery.php", window.location.href);
      u.searchParams.set("event_id", String(eventId));
      u.searchParams.set("token", token);
      u.searchParams.set("since_id", String(since));
      const r = await fetch(u.toString(), { credentials: "same-origin" });
      const j = await r.json();
      if (j.ok && Array.isArray(j.items)) mergeItems(j.items);
      if (j.last_id) state.lastId = j.last_id;
    }

    fetchGallery(0);

    setInterval(() => fetchGallery(state.lastId), pollMs);

    window.addEventListener("gallery:new", (ev) => {
      const it = ev.detail;
      if (it && it.id) mergeItems([it]);
    });

    async function toggleLike(mediaId, btn) {
      const r = await fetch("like.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ media_id: mediaId, event_id: eventId, token }),
        credentials: "same-origin",
      });
      const j = await r.json();
      if (j.ok) {
        btn.textContent = "❤ " + j.likes;
        btn.classList.toggle("liked", j.liked);
      }
    }

    function getItemsForSlideshow() {
      return Array.from(state.byId.values())
        .map((x) => x.data)
        .filter((d) => d.kind === "image");
    }

    return { getItemsForSlideshow, state };
  }

  function notifyNew(it) {
    if (!("Notification" in window) || Notification.permission !== "granted") return;
    try {
      new Notification("Nuevo recuerdo en el evento", {
        body: it.guest_name + " subió una foto o video.",
        silent: true,
      });
    } catch {}
  }

  function maybeAskNotify() {
    if (!("Notification" in window)) return;
    if (Notification.permission !== "default") return;
    setTimeout(() => {
      Notification.requestPermission().catch(() => {});
    }, 4000);
  }

  function openLightbox(it) {
    const modal = document.getElementById("lightbox");
    const body = document.getElementById("modalBody");
    if (!modal || !body) return;
    body.innerHTML = "";
    if (it.kind === "video") {
      const v = document.createElement("video");
      v.src = it.url;
      v.controls = true;
      v.autoplay = true;
      body.appendChild(v);
    } else {
      const im = document.createElement("img");
      im.src = it.url;
      im.alt = "";
      body.appendChild(im);
    }
    modal.classList.remove("hidden");
    loadCommentsIntoModal(it.id, body);
  }

  async function loadCommentsIntoModal(mediaId, body) {
    const eventId = parseInt(document.body.dataset.eventId || "0", 10);
    const token = document.body.dataset.uploadToken || "";
    const u = new URL("comments.php", window.location.href);
    u.searchParams.set("media_id", String(mediaId));
    u.searchParams.set("event_id", String(eventId));
    u.searchParams.set("token", token);
    try {
      const r = await fetch(u.toString());
      const j = await r.json();
      if (!j.ok || !j.comments?.length) return;
      const box = document.createElement("div");
      box.style.marginTop = "1rem";
      box.style.maxHeight = "160px";
      box.style.overflow = "auto";
      j.comments.forEach((c) => {
        const p = document.createElement("p");
        p.style.fontSize = "0.9rem";
        p.style.color = "rgba(255,255,255,0.75)";
        p.textContent = c.guest_name + ": " + c.body;
        box.appendChild(p);
      });
      body.appendChild(box);
    } catch {}
  }

  function initLightbox() {
    const modal = document.getElementById("lightbox");
    const close = document.getElementById("modalClose");
    close?.addEventListener("click", () => modal?.classList.add("hidden"));
    modal?.addEventListener("click", (e) => {
      if (e.target === modal) modal.classList.add("hidden");
    });
  }

  function initCommentModal(eventId, token) {
    const modal = document.getElementById("commentModal");
    const form = document.getElementById("commentForm");
    const cancel = document.getElementById("commentCancel");
    const mid = document.getElementById("commentMediaId");

    window.openCommentModal = function (mediaId) {
      if (mid) mid.value = String(mediaId);
      modal?.classList.remove("hidden");
    };

    cancel?.addEventListener("click", () => modal?.classList.add("hidden"));

    form?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const body = {
        media_id: parseInt(String(mid?.value || "0"), 10),
        event_id: eventId,
        token,
        guest_name: String(fd.get("guest_name") || ""),
        body: String(fd.get("body") || ""),
      };
      const r = await fetch("comment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
        credentials: "same-origin",
      });
      const j = await r.json();
      if (j.ok) {
        modal?.classList.add("hidden");
        form.reset();
      } else {
        alert(j.error || "No se pudo comentar.");
      }
    });
  }

  function initShare(shareUrl) {
    document.getElementById("btnShareEvent")?.addEventListener("click", async () => {
      const payload = { title: document.title, text: "Mira la galería del evento", url: shareUrl };
      if (navigator.share) {
        try {
          await navigator.share(payload);
        } catch {}
      } else {
        await navigator.clipboard.writeText(shareUrl);
        alert("Enlace copiado al portapapeles.");
      }
    });
  }

  function initSlideshowTv(gallery) {
    const btn = document.getElementById("btnSlideshow");
    const stage = document.getElementById("tvStage");
    const body = document.body;
    if (!btn || !stage || !gallery) return;
    if (body.classList.contains("mode-tv")) {
      btn.style.display = "none";
    }

    let idx = 0;
    let timer = null;

    function showSlide(items) {
      if (!items.length) return;
      idx = idx % items.length;
      const it = items[idx];
      stage.innerHTML = "";
      if (it.kind === "video") {
        const v = document.createElement("video");
        v.src = it.url;
        v.autoplay = true;
        v.muted = true;
        v.loop = true;
        v.playsInline = true;
        stage.appendChild(v);
      } else {
        const im = document.createElement("img");
        im.src = it.url;
        im.alt = "";
        stage.appendChild(im);
      }
    }

    function loop() {
      const imgs = gallery.getItemsForSlideshow();
      const items =
        imgs.length > 0
          ? imgs
          : Array.from(gallery.state.byId.values()).map((x) => x.data);
      if (!items.length) return;
      showSlide(items);
      idx++;
    }

    btn.addEventListener("click", () => {
      const on = body.classList.toggle("slideshow-on");
      if (on) {
        loop();
        timer = setInterval(loop, body.classList.contains("mode-tv") ? 6000 : 5000);
      } else {
        clearInterval(timer);
        timer = null;
        stage.innerHTML = "";
      }
    });

    if (body.classList.contains("mode-tv")) {
      body.classList.add("slideshow-on");
      loop();
      timer = setInterval(loop, 6000);
    }
  }
})();
