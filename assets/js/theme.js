/* =============================================
   THEME.JS — Dark/Light toggle + Ripple + Animasi
   ============================================= */

(function () {
  'use strict';

  /* ---- 1. THEME INIT ---- */
  const STORAGE_KEY = 'absen_theme';

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    document.body.setAttribute('data-theme', theme);
    localStorage.setItem(STORAGE_KEY, theme);
    updateToggleIcon(theme);
  }

  function updateToggleIcon(theme) {
    const btn = document.getElementById('themeToggle');
    if (!btn) return;
    const sun  = btn.querySelector('.icon-sun');
    const moon = btn.querySelector('.icon-moon');
    if (!sun || !moon) return;
    if (theme === 'dark') {
      sun.style.cssText  = 'opacity:1;transform:rotate(0deg)';
      moon.style.cssText = 'opacity:0;position:absolute;pointer-events:none';
      btn.setAttribute('title', 'Ganti ke mode terang');
    } else {
      sun.style.cssText  = 'opacity:0;position:absolute;pointer-events:none';
      moon.style.cssText = 'opacity:1;transform:rotate(0deg)';
      btn.setAttribute('title', 'Ganti ke mode gelap');
    }
  }

  function getSavedTheme() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) return saved;
    // Ikuti preferensi sistem
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  // Terapkan tema sedini mungkin (sebelum paint) untuk mencegah flash
  const initialTheme = getSavedTheme();
  document.documentElement.setAttribute('data-theme', initialTheme);

  /* ---- 2. DOM READY ---- */
  document.addEventListener('DOMContentLoaded', function () {

    // Apply properly after DOM loaded
    applyTheme(getSavedTheme());

    /* ---- Toggle button ---- */
    const toggleBtn = document.getElementById('themeToggle');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next    = current === 'dark' ? 'light' : 'dark';

        // Smooth transition class
        document.body.classList.add('theme-transitioning');
        setTimeout(() => document.body.classList.remove('theme-transitioning'), 400);

        applyTheme(next);

        // Animasi tombol toggle
        toggleBtn.classList.remove('btn-press');
        void toggleBtn.offsetWidth;
        toggleBtn.classList.add('btn-press');
      });
    }

    /* ---- 3. RIPPLE EFFECT pada semua .btn ---- */
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.btn');
      if (!btn || btn.disabled) return;

      // Ripple
      const circle = document.createElement('span');
      const diameter = Math.max(btn.clientWidth, btn.clientHeight);
      const rect   = btn.getBoundingClientRect();
      const x = e.clientX - rect.left - diameter / 2;
      const y = e.clientY - rect.top  - diameter / 2;

      circle.classList.add('ripple-circle');
      circle.style.cssText = `
        width:${diameter}px;
        height:${diameter}px;
        left:${x}px;
        top:${y}px;
      `;
      btn.appendChild(circle);
      setTimeout(() => circle.remove(), 600);

      // Pop animation
      btn.classList.remove('btn-press');
      void btn.offsetWidth;
      btn.classList.add('btn-press');
    });

    /* ---- 4. SUBMIT BUTTON LOADING STATE ---- */
    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function () {
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn && !submitBtn.dataset.noLoading) {
          submitBtn.classList.add('btn-loading');
          // safety reset
          setTimeout(() => submitBtn.classList.remove('btn-loading'), 8000);
        }
      });
    });

    /* ---- 5. CARD HOVER TILT (tile cards) ---- */
    document.querySelectorAll('.tile').forEach(function (card) {
      card.addEventListener('mousemove', function (e) {
        const rect   = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const cx = rect.width  / 2;
        const cy = rect.height / 2;
        const rotX =  ((y - cy) / cy) * 3;   // max 3deg
        const rotY = -((x - cx) / cx) * 3;
        card.style.transform = `perspective(600px) rotateX(${rotX}deg) rotateY(${rotY}deg) translateY(-4px)`;
      });
      card.addEventListener('mouseleave', function () {
        card.style.transform = '';
      });
    });

    /* ---- 6. NAVBAR SHRINK on scroll ---- */
    const navbar = document.querySelector('.navbar');
    if (navbar) {
      window.addEventListener('scroll', function () {
        if (window.scrollY > 10) {
          navbar.style.padding = '.35rem 0';
          navbar.style.boxShadow = '0 6px 24px rgba(0,0,0,.28)';
        } else {
          navbar.style.padding = '';
          navbar.style.boxShadow = '';
        }
      }, { passive: true });
    }

  }); // end DOMContentLoaded

})();
