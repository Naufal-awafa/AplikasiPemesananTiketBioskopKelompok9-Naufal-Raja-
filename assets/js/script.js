/* =====================================================================
   SINEVERSE CINEMA — script.js
   Micro-interactions: mouse glow, scroll-reveal, seat picker, tabs,
   payment selector, dan util kecil lain.
   ===================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form[method="POST"], form[method="post"]').forEach((form) => {
    if (!form.querySelector('input[name="csrf_token"]')) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'csrf_token';
      input.value = window.SINEVERSE_CSRF || '';
      form.prepend(input);
    }
  });
  // Setiap init dibungkus try/catch agar bila satu fitur error,
  // fitur lain (termasuk menu akun) tetap jalan.
  const safe = (fn) => {
    try {
      fn();
    } catch (e) {
      console.warn('[init] gagal:', e);
    }
  };
  safe(initAccountMenu); // paling penting: menu akun harus selalu aktif
  safe(initHamburger);
  safe(initPreloader);
  safe(initScrollReveal);
  safe(initRevealFailsafe);
  safe(initReservationCountdown);
  safe(initTabFilterFilm);
  safe(initMovieQuickModal);
  safe(initSeatPicker);
  safe(initPaymentPicker);
  safe(initCountUp);
  safe(initRandomFontEffect);
  safe(initAutoAlertDismiss);
});

function initReservationCountdown() {
  const target = document.getElementById('reservation-countdown');
  if (!target) return;
  let detik = Math.max(1, Number(target.dataset.minutes || 10)) * 60;
  const render = () => {
    const menit = Math.floor(detik / 60);
    const sisa = String(detik % 60).padStart(2, '0');
    target.textContent = `selama ${menit}:${sisa}`;
    if (detik <= 0) {
      target.textContent = 'telah berakhir';
      document.querySelectorAll('button[type="submit"]').forEach((btn) => (btn.disabled = true));
      return;
    }
    detik--;
    setTimeout(render, 1000);
  };
  render();
}

/* ---------------- Preloader: hitung persen + partikel + logo muncul (gaya splash bioskop) ---------------- */
function initPreloader() {
  const pre = document.getElementById('preloader');
  if (!pre) return;

  // Tampilkan splash hanya sekali per tab agar tidak menghambat pengguna
  // setiap kali kembali ke beranda.
  if (sessionStorage.getItem('sineverse_preloader_seen')) {
    pre.remove();
    return;
  }
  sessionStorage.setItem('sineverse_preloader_seen', '1');

  // Failsafe: jika setelah 4 detik preloader masih ada (JS macet / error),
  // paksa sembunyikan agar tidak menutupi tombol halaman.
  const failsafe = setTimeout(() => {
    if (pre && pre.isConnected && !pre.classList.contains('hide')) {
      pre.classList.add('hide');
      setTimeout(() => {
        if (pre.isConnected) pre.remove();
      }, 800);
    }
  }, 2500);

  // Partikel mengambang di background preloader
  const wadahPartikel = document.getElementById('preloader-particles');
  if (wadahPartikel) {
    for (let i = 0; i < 40; i++) {
      const p = document.createElement('span');
      p.className = 'pre-particle';
      p.style.left = Math.random() * 100 + '%';
      p.style.bottom = -(Math.random() * 60) + 'px';
      const ukuran = Math.random() * 3 + 1;
      p.style.width = ukuran + 'px';
      p.style.height = ukuran + 'px';
      p.style.opacity = Math.random().toFixed(2);
      p.style.animationDuration = 8 + Math.random() * 10 + 's';
      p.style.animationDelay = -(Math.random() * 15) + 's';
      wadahPartikel.appendChild(p);
    }
  }

  const elLogo = document.getElementById('preloader-logo-wrap');

  // Logo tampil langsung dengan animasi CSS (lihat preloaderLogoIn di style.css).
  // Preloader ditahan sebentar lalu memudar keluar - tanpa hitungan persen.
  setTimeout(() => {
    pre.classList.add('hide');
    clearTimeout(failsafe);
  }, 850);
  setTimeout(() => {
    if (pre.isConnected) pre.remove();
  }, 1450);
}

/* ---------------- Failsafe reveal: kalau IntersectionObserver tidak jalan,
   pastikan semua elemen .reveal langsung tampil (tidak invisible permanen). ---------------- */
function initRevealFailsafe() {
  if (!('IntersectionObserver' in window)) {
    document.querySelectorAll('.reveal').forEach((el) => el.classList.add('show'));
    return;
  }
  // Cadangan: elemen reveal yang belum terlihat setelah 3 detik tetap ditampilkan
  // (mis. konten di bawah fold tapi observer gagal trigger karena layout aneh).
  setTimeout(() => {
    document.querySelectorAll('.reveal:not(.show)').forEach((el) => {
      const r = el.getBoundingClientRect();
      if (r.top < window.innerHeight + 200) el.classList.add('show');
    });
  }, 3000);
}

/* ---------------- Mouse glow mengikuti kursor (mouse-glow div) ---------------- */
function initMouseGlow() {
  const glow = document.querySelector('.mouse-glow');
  if (!glow) return;
  let targetX = window.innerWidth / 2,
    targetY = window.innerHeight / 2;
  let curX = targetX,
    curY = targetY;

  window.addEventListener('mousemove', (e) => {
    targetX = e.clientX;
    targetY = e.clientY;
  });

  function animate() {
    curX += (targetX - curX) * 0.08;
    curY += (targetY - curY) * 0.08;
    glow.style.transform = `translate(${curX}px, ${curY}px) translate(-50%,-50%)`;
    requestAnimationFrame(animate);
  }
  animate();
}

/* ---------------- Reveal elemen saat masuk viewport ---------------- */
function initScrollReveal() {
  const targets = document.querySelectorAll('.reveal');
  if (!('IntersectionObserver' in window) || targets.length === 0) {
    targets.forEach((el) => el.classList.add('show'));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry, idx) => {
        if (!entry.isIntersecting) return;
        const i = Array.from(targets).indexOf(entry.target);
        const delay = Math.min(i * 45, 600);
        setTimeout(() => entry.target.classList.add('show'), delay);
        observer.unobserve(entry.target);
      });
    },
    {
      threshold: 0.1,
      rootMargin: '0px 0px -30px 0px',
    },
  );

  targets.forEach((el) => observer.observe(el));
}

/* ---------------- Tab filter daftar film (Sedang Tayang / Akan Datang) ---------------- */
function initTabFilterFilm() {
  const tabButtons = document.querySelectorAll('[data-tab-target]');
  if (tabButtons.length === 0) return;
  const statusInput = document.getElementById('film-status-filter');

  const applyFilter = (target) => {
    tabButtons.forEach((button) => {
      button.classList.toggle('active', button.getAttribute('data-tab-target') === target);
    });

    if (statusInput) statusInput.value = target === 'semua' ? '' : target;

    document.querySelectorAll('[data-tab-group]').forEach((card) => {
      const match = target === 'semua' || card.getAttribute('data-tab-group') === target;
      card.style.display = match ? '' : 'none';
      if (match) card.classList.add('show');
    });
  };

  tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      applyFilter(btn.getAttribute('data-tab-target'));
    });
  });

  const activeTab = document.querySelector('[data-tab-target].active') || tabButtons[0];
  applyFilter(activeTab.getAttribute('data-tab-target'));
}

/* ---------------- Modal detail cepat dari kartu film ---------------- */
function initMovieQuickModal() {
  const modal = document.getElementById('movie-quick-modal');
  const cards = document.querySelectorAll('[data-film-modal]');
  if (!modal || cards.length === 0) return;

  const poster = document.getElementById('movie-modal-poster');
  const title = document.getElementById('movie-modal-title');
  const meta = document.getElementById('movie-modal-meta');
  const synopsis = document.getElementById('movie-modal-synopsis');
  const booking = document.getElementById('movie-modal-booking');
  const closeButton = modal.querySelector('.movie-modal-close');
  let previousFocus = null;
  let closeTimer = null;

  const closeModal = (afterClose) => {
    if (modal.hidden) return;
    clearTimeout(closeTimer);
    modal.classList.remove('is-open');
    document.body.classList.remove('movie-modal-open');
    closeTimer = setTimeout(() => {
      modal.hidden = true;
      if (typeof afterClose === 'function') {
        afterClose();
      } else if (previousFocus) {
        previousFocus.focus({ preventScroll: true });
      }
    }, 200);
  };

  const openModal = (card) => {
    let film;
    try {
      film = JSON.parse(card.dataset.filmModal || '{}');
    } catch (error) {
      console.warn('Data modal film tidak valid.', error);
      window.location.href = card.href;
      return;
    }

    previousFocus = card;
    title.textContent = film.title || 'Detail Film';
    synopsis.textContent = film.synopsis || 'Sinopsis resmi belum tersedia.';
    poster.src = film.poster || '';
    poster.alt = film.poster ? `Poster ${film.title || 'film'}` : '';

    const defaultUrl = film.bookingUrl || card.href;
    booking.href = defaultUrl;

    meta.innerHTML = '';
    [film.genre, film.durasi, film.ratingUsia, film.bahasa].forEach((item) => {
      if (!item) return;
      const span = document.createElement('span');
      span.textContent = item;
      meta.appendChild(span);
    });

    clearTimeout(closeTimer);
    modal.hidden = false;
    document.body.classList.add('movie-modal-open');
    requestAnimationFrame(() => {
      modal.classList.add('is-open');
      closeButton.focus({ preventScroll: true });
    });
  };

  cards.forEach((card) => {
    card.addEventListener('click', (event) => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      openModal(card);
    });
  });

  modal.querySelectorAll('[data-movie-modal-close]').forEach((button) => {
    button.addEventListener('click', () => closeModal());
  });

  booking.addEventListener('click', (event) => {
    event.preventDefault();
    const destination = booking.href;
    closeModal(() => {
      window.location.href = destination;
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) closeModal();
  });
}

/* ---------------- Pemilihan kursi interaktif ---------------- */
function initSeatPicker() {
  const seats = document.querySelectorAll('.seat:not(.taken)');
  const hiddenInput = document.getElementById('kursi-terpilih');
  const summaryList = document.getElementById('ringkasan-kursi');
  const totalHargaEl = document.getElementById('ringkasan-total');
  const btnLanjut = document.getElementById('btn-lanjut-checkout');

  if (seats.length === 0) return;

  const hargaReguler = parseInt(document.body.dataset.hargaReguler || '0', 10);
  const hargaVip = parseInt(document.body.dataset.hargaVip || '0', 10);
  const maxKursi = 6;
  let terpilih = [];

  function updateRingkasan() {
    if (hiddenInput) hiddenInput.value = terpilih.map((s) => s.dataset.id).join(',');

    if (summaryList) {
      summaryList.innerHTML =
        terpilih.length === 0
          ? '<div class="text-muted" style="font-size:.85rem;">Belum ada kursi dipilih</div>'
          : terpilih
              .map((s) => {
                const harga = s.classList.contains('vip') ? hargaVip : hargaReguler;
                return `<div class="summary-row"><span>Kursi ${s.dataset.label} (${s.classList.contains('vip') ? 'VIP' : 'Reguler'})</span><span>${formatRupiahJS(harga)}</span></div>`;
              })
              .join('');
    }

    const total = terpilih.reduce((acc, s) => acc + (s.classList.contains('vip') ? hargaVip : hargaReguler), 0);
    document.body.dataset.ticketTotal = String(total);
    document.dispatchEvent(new CustomEvent('ticket-total-change'));
    if (totalHargaEl && !document.getElementById('ringkasan-produk')) totalHargaEl.textContent = formatRupiahJS(total);
    if (btnLanjut) btnLanjut.disabled = terpilih.length === 0;
  }

  seats.forEach((seat) => {
    seat.addEventListener('click', () => toggleSeat(seat));
    // Aksesibilitas keyboard: Enter / Space memilih kursi
    seat.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggleSeat(seat);
      }
    });
  });

  function toggleSeat(seat) {
    if (seat.classList.contains('taken')) return;
    const sudahAda = terpilih.includes(seat);
    if (sudahAda) {
      terpilih = terpilih.filter((s) => s !== seat);
      seat.classList.remove('selected');
      seat.setAttribute('aria-pressed', 'false');
    } else {
      if (terpilih.length >= maxKursi) {
        tampilkanToast(`Maksimal ${maxKursi} kursi per pemesanan`);
        return;
      }
      terpilih.push(seat);
      seat.classList.add('selected');
      seat.setAttribute('aria-pressed', 'true');
    }
    updateRingkasan();
  }

  updateRingkasan();
}

function formatRupiahJS(angka) {
  return 'Rp' + angka.toLocaleString('id-ID');
}

/* ---------------- Pemilihan metode pembayaran ---------------- */
function initPaymentPicker() {
  const options = document.querySelectorAll('.payment-option');
  if (options.length === 0) return;

  options.forEach((opt) => {
    opt.addEventListener('click', () => {
      options.forEach((o) => o.classList.remove('checked'));
      opt.classList.add('checked');
      const radio = opt.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;

      document.querySelectorAll('.payment-detail-field').forEach((f) => (f.style.display = 'none'));
      const detail = document.getElementById('detail-' + opt.dataset.metode);
      if (detail) detail.style.display = 'block';
    });
  });
}

/* ---------------- Hamburger menu mobile ---------------- */
function initHamburger() {
  const btn = document.querySelector('.hamburger');
  const nav = document.querySelector('.nav-links');
  if (!btn || !nav) return;
  btn.addEventListener('click', () => {
    nav.classList.toggle('mobile-open');
  });
}

/* ---------------- Menu akun dropdown ---------------- */
function initAccountMenu() {
  const menu = document.querySelector('.account-menu');
  if (!menu) return;
  const trigger = menu.querySelector('.account-trigger');
  const dropdown = menu.querySelector('.account-dropdown');

  function open(state) {
    menu.classList.toggle('open', state);
    trigger.setAttribute('aria-expanded', String(state));
  }

  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    open(!menu.classList.contains('open'));
  });

  // Tutup kalau klik di luar menu
  document.addEventListener('click', (e) => {
    if (!menu.contains(e.target)) open(false);
  });

  // Tutup pakai Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') open(false);
  });

  // Saat dropdown terbuka, fokus ke item pertama (aksesibilitas keyboard)
  const firstItem = dropdown ? dropdown.querySelector('.account-item') : null;
  trigger.addEventListener('keydown', (e) => {
    if ((e.key === 'ArrowDown' || e.key === 'Enter') && firstItem) {
      e.preventDefault();
      open(true);
      firstItem.focus();
    }
  });
}

/* ---------------- Animasi angka naik (count up) untuk statistik ---------------- */
function initCountUp() {
  const targets = document.querySelectorAll('[data-countup]');
  targets.forEach((el) => {
    const target = parseInt(el.dataset.countup, 10);
    const duration = 1200;
    const start = performance.now();

    function step(now) {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.floor(eased * target).toLocaleString('id-ID');
      if (progress < 1) requestAnimationFrame(step);
      else el.textContent = target.toLocaleString('id-ID');
    }
    requestAnimationFrame(step);
  });
}

/* ---------------- Toast notifikasi ringan ---------------- */
function tampilkanToast(pesan) {
  let toast = document.getElementById('global-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'global-toast';
    toast.style.cssText = `
            position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(20px);
            background: rgba(18,18,26,.92); backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,.14); color: #fff;
            padding: 13px 22px; border-radius: 999px; font-size: .86rem; font-weight: 600;
            z-index: 9999; opacity: 0; transition: all .35s cubic-bezier(.16,1,.3,1);
            box-shadow: 0 10px 30px rgba(0,0,0,.4);
        `;
    document.body.appendChild(toast);
  }
  toast.textContent = pesan;
  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(-50%) translateY(0)';
  });
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(-50%) translateY(20px)';
  }, 2600);
}

/* ---------------- Auto-dismiss alert box setelah beberapa detik ---------------- */
function initAutoAlertDismiss() {
  document.querySelectorAll('.alert[data-autohide]').forEach((alert) => {
    setTimeout(() => {
      alert.style.transition = 'opacity .5s ease, transform .5s ease';
      alert.style.opacity = '0';
      alert.style.transform = 'translateY(-8px)';
      setTimeout(() => alert.remove(), 500);
    }, 4000);
  });
}

/* ---------------- Custom cursor (gaya gendesign) ---------------- */
function initCustomCursor() {
  const dot = document.querySelector('.cursor-dot');
  const ring = document.querySelector('.cursor-ring');
  const text = document.querySelector('.cursor-text');
  if (!dot || !ring) return;

  let mouseX = window.innerWidth / 2;
  let mouseY = window.innerHeight / 2;
  let ringX = mouseX;
  let ringY = mouseY;

  document.addEventListener('mousemove', (e) => {
    mouseX = e.clientX;
    mouseY = e.clientY;
    dot.style.left = mouseX + 'px';
    dot.style.top = mouseY + 'px';
  });

  function animateCursor() {
    ringX += (mouseX - ringX) * 0.18;
    ringY += (mouseY - ringY) * 0.18;
    ring.style.left = ringX + 'px';
    ring.style.top = ringY + 'px';
    requestAnimationFrame(animateCursor);
  }
  animateCursor();

  const hoverTargets = document.querySelectorAll('a, button, .btn, .seat, .payment-option, [data-tab-target]');
  hoverTargets.forEach((item) => {
    item.addEventListener('mouseenter', () => {
      ring.classList.add('active');
      dot.classList.add('hide');
      if (text) text.textContent = 'CLICK';
    });
    item.addEventListener('mouseleave', () => {
      ring.classList.remove('active');
      dot.classList.remove('hide');
      if (text) text.textContent = '';
    });
  });
}

/* ---------------- Random font effect untuk heading tertentu (gaya gendesign) ---------------- */
function initRandomFontEffect() {
  const targets = document.querySelectorAll('.random-font-target');
  if (!targets.length) return;

  const fonts = [
    'Poppins',
    'Righteous',
    'Bebas Neue',
    'Playfair Display',
    'Caveat',
    'Pacifico',
    'Orbitron',
    'Space Grotesk',
    'Anton',
    'Dancing Script',
    'Abril Fatface',
    'Courier Prime',
    'Permanent Marker',
  ];

  targets.forEach((el) => {
    let idx = 0;
    const interval = parseInt(el.dataset.fontInterval || '180', 10);
    const timer = setInterval(() => {
      idx = (idx + 1) % fonts.length;
      el.style.fontFamily = fonts[idx];
    }, interval);
  });
}
