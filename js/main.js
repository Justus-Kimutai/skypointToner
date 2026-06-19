/* =====================================================
   SKYPOINT TONERS — Main JavaScript
   ===================================================== */

const WA_NUMBER = '254702715346';

/* ===== THEME TOGGLE ===== */
(function () {
  const html = document.documentElement;
  const saved = localStorage.getItem('skypoint-theme') || 'light';
  html.setAttribute('data-theme', saved);

  function setIcon(theme) {
    const btn = document.getElementById('themeToggle');
    if (!btn) return;
    const icon = btn.querySelector('i');
    if (icon) icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
  }
  setIcon(saved);

  document.addEventListener('DOMContentLoaded', function () {
    setIcon(html.getAttribute('data-theme'));

    const btn = document.getElementById('themeToggle');
    btn?.addEventListener('click', function () {
      const current = html.getAttribute('data-theme');
      const next = current === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      localStorage.setItem('skypoint-theme', next);
      setIcon(next);
    });
  });
})();

document.addEventListener('DOMContentLoaded', function () {

  /* ===== STICKY HEADER ===== */
  const header = document.querySelector('.header');
  let lastScroll = 0;
  window.addEventListener('scroll', function () {
    const y = window.scrollY;
    if (header) {
      header.classList.toggle('scrolled', y > 60);
    }
    lastScroll = y;
  }, { passive: true });

  /* ===== MOBILE MENU ===== */
  const menuToggle = document.getElementById('menuToggle');
  const mobileMenu = document.getElementById('mobileMenu');
  const mobileClose = document.getElementById('mobileMenuClose');

  function openMenu() {
    mobileMenu?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeMenu() {
    mobileMenu?.classList.remove('open');
    document.body.style.overflow = '';
  }

  menuToggle?.addEventListener('click', openMenu);
  mobileClose?.addEventListener('click', closeMenu);
  mobileMenu?.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));

  /* ===== ACTIVE NAV ===== */
  const page = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a, .mobile-nav-links a').forEach(function (a) {
    const href = a.getAttribute('href');
    if (href === page || (page === '' && href === 'index.html') || (page === 'index.html' && href === 'index.html')) {
      a.classList.add('active');
    }
  });

  /* ===== BACK TO TOP ===== */
  const btt = document.getElementById('backToTop');
  window.addEventListener('scroll', function () {
    btt?.classList.toggle('visible', window.scrollY > 500);
  }, { passive: true });
  btt?.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  /* ===== SCROLL REVEAL ===== */
  const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale');
  if (revealEls.length) {
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

    revealEls.forEach(function (el) { observer.observe(el); });
  }

  /* ===== STATS COUNTER ===== */
  const counters = document.querySelectorAll('[data-count]');
  if (counters.length) {
    const counterObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animateCount(entry.target);
          counterObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.6 });

    counters.forEach(function (el) { counterObserver.observe(el); });
  }

  function animateCount(el) {
    const target = parseInt(el.getAttribute('data-count'), 10);
    const suffix = el.getAttribute('data-suffix') || '';
    const prefix = el.getAttribute('data-prefix') || '';
    const duration = 1800;
    const steps = 60;
    const increment = target / steps;
    let current = 0;
    let step = 0;

    const timer = setInterval(function () {
      step++;
      current = Math.min(Math.round(increment * step), target);
      el.textContent = prefix + current.toLocaleString() + suffix;
      if (step >= steps) clearInterval(timer);
    }, duration / steps);
  }

  /* ===== PRODUCT FILTER (homepage tabs) ===== */
  const filterTabs = document.querySelectorAll('.filter-tab');
  const productCards = document.querySelectorAll('[data-category]');

  filterTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      filterTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const cat = tab.getAttribute('data-filter');
      filterProducts(cat);
    });
  });

  function filterProducts(cat) {
    productCards.forEach(function (card) {
      const cardCat = card.getAttribute('data-category');
      const show = cat === 'all' || cardCat === cat;
      card.style.display = show ? '' : 'none';
      if (show) {
        card.style.animation = 'none';
        card.offsetHeight; // reflow
        card.style.animation = '';
      }
    });
    updateCount();
  }

  /* ===== SIDEBAR FILTER (products page) ===== */
  const sidebarBtns = document.querySelectorAll('.sidebar-btn');
  sidebarBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      sidebarBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const cat = btn.getAttribute('data-filter');
      filterProducts(cat);
      filterTabs.forEach(t => t.classList.toggle('active', t.getAttribute('data-filter') === cat));
    });
  });

  /* ===== PRODUCT SEARCH ===== */
  const searchInput = document.getElementById('productSearch');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      productCards.forEach(function (card) {
        const text = card.textContent.toLowerCase();
        card.style.display = (!q || text.includes(q)) ? '' : 'none';
      });
      updateCount();
    });
  }

  function updateCount() {
    const counter = document.getElementById('productCount');
    if (!counter) return;
    const visible = document.querySelectorAll('[data-category]:not([style*="display: none"])').length;
    counter.textContent = visible + ' product' + (visible !== 1 ? 's' : '');
  }

  /* ===== WHATSAPP ===== */
  function orderWhatsApp(productName, price) {
    const msg = price
      ? `Hello Skypoint! 👋\n\nI'm interested in ordering:\n\n📦 *${productName}*\n💰 *${price}*\n\nPlease confirm availability and share more details.\n\nThank you!`
      : `Hello Skypoint! 👋\n\nI'm interested in:\n\n📦 *${productName}*\n\nKindly share the pricing and availability.\n\nThank you!`;
    window.open('https://wa.me/' + WA_NUMBER + '?text=' + encodeURIComponent(msg), '_blank');
  }

  function generalInquiry() {
    const msg = 'Hello Skypoint! 👋\n\nI\'d like to inquire about your products and services.\n\nPlease get back to me. Thank you!';
    window.open('https://wa.me/' + WA_NUMBER + '?text=' + encodeURIComponent(msg), '_blank');
  }

  document.querySelectorAll('[data-wa-product]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      orderWhatsApp(this.getAttribute('data-wa-product'), this.getAttribute('data-wa-price') || '');
    });
  });

  document.querySelectorAll('[data-wa-general]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      generalInquiry();
    });
  });

  /* ===== HIGHLIGHT TODAY IN HOURS ===== */
  const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
  const today = days[new Date().getDay()];
  const todayRow = document.querySelector('[data-day="' + today + '"]');
  if (todayRow) todayRow.classList.add('today');

  /* ===== HERO VIDEO LAZY PLAY ===== */
  const heroVid = document.getElementById('heroVid');
  if (heroVid) {
    const vidObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          heroVid.play().catch(function () {});
          heroVid.addEventListener('playing', function () {
            const placeholder = document.getElementById('heroVidPlaceholder');
            if (placeholder) placeholder.style.opacity = '0';
          }, { once: true });
          vidObserver.unobserve(heroVid);
        }
      });
    }, { threshold: 0.25 });
    vidObserver.observe(heroVid);
  }

  /* ===== SMOOTH SCROLL for anchor links ===== */
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      const id = this.getAttribute('href').slice(1);
      const target = document.getElementById(id);
      if (target) {
        e.preventDefault();
        const offset = 80;
        const top = target.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top, behavior: 'smooth' });
      }
    });
  });

}); // end DOMContentLoaded
