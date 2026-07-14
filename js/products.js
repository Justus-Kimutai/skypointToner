/* =====================================================
   SKYPOINT TONERS — Products Page Renderer
   Fetches products.json and builds the category sidebar,
   filter tabs and product grid so the client can add/remove
   products (and even whole new categories) via the admin
   panel without touching HTML.
   ===================================================== */

(function () {
  const grid = document.querySelector('.products-grid');
  if (!grid) return;

  const sidebarList = document.getElementById('categorySidebarList');
  const filterTabsList = document.getElementById('categoryFilterTabs');

  function esc(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function priceHtml(p) {
    if (p.priceType === 'fixed') {
      return '<span class="ksh">KSh</span> ' + esc(p.price);
    }
    if (p.priceType === 'from') {
      return 'From <span class="ksh">KSh</span> ' + esc(p.price);
    }
    return '<span class="quote">' + esc(p.quoteLabel || 'Get Quote') + '</span>';
  }

  function waPrice(p) {
    if (p.priceType === 'fixed') return 'KSh ' + p.price;
    if (p.priceType === 'from') return 'From KSh ' + p.price;
    return '';
  }

  function cardHtml(p) {
    const badge = p.badge
      ? '<div class="product-badges"><span class="badge badge-accent">' + esc(p.badge) + '</span></div>'
      : '';
    const photo = p.image
      ? '<img class="product-photo" src="' + esc(p.image) + '" alt="' + esc(p.name) + '" loading="lazy">'
      : '<div class="product-image-inner"><i class="fas fa-box"></i></div>';
    return (
      '<div class="product-card" data-category="' + esc(p.category) + '">' +
        '<div class="product-image">' +
          badge +
          photo +
        '</div>' +
        '<div class="product-body">' +
          '<div class="product-cat">' + esc(p.categoryLabel) + '</div>' +
          '<div class="product-name">' + esc(p.name) + '</div>' +
          '<div class="product-specs">' + esc(p.specs) + '</div>' +
          '<div class="product-footer">' +
            '<div class="product-price">' + priceHtml(p) + '</div>' +
            '<button class="product-wa-btn" data-wa-product="' + esc(p.waProduct || p.name) + '" data-wa-price="' + esc(waPrice(p)) + '" title="Order via WhatsApp">' +
              '<i class="fab fa-whatsapp"></i>' +
            '</button>' +
          '</div>' +
        '</div>' +
      '</div>'
    );
  }

  function renderCategoryUI(categories, products) {
    const counts = { all: products.length };
    Object.keys(categories).forEach(function (slug) { counts[slug] = 0; });
    products.forEach(function (p) {
      if (counts[p.category] === undefined) counts[p.category] = 0;
      counts[p.category]++;
    });

    // Only show categories that currently have at least one product.
    const activeSlugs = Object.keys(categories).filter(function (slug) { return counts[slug] > 0; });

    if (sidebarList) {
      sidebarList.innerHTML = activeSlugs.map(function (slug) {
        return '<button class="sidebar-btn" data-filter="' + esc(slug) + '">' +
          esc(categories[slug]) + ' <span class="cnt">' + counts[slug] + '</span></button>';
      }).join('');
    }
    if (filterTabsList) {
      filterTabsList.innerHTML = activeSlugs.map(function (slug) {
        return '<button class="filter-tab" data-filter="' + esc(slug) + '">' + esc(categories[slug]) + '</button>';
      }).join('');
    }

    const allSidebarBtn = document.querySelector('.sidebar-btn[data-filter="all"] .cnt');
    if (allSidebarBtn) allSidebarBtn.textContent = counts.all;

    const productCount = document.getElementById('productCount');
    if (productCount) {
      productCount.textContent = counts.all + ' product' + (counts.all !== 1 ? 's' : '');
    }
  }

  fetch('products.json')
    .then(function (res) {
      if (!res.ok) throw new Error('Failed to load products.json');
      return res.json();
    })
    .then(function (data) {
      const categories = data.categories || {};
      const products = Array.isArray(data.products) ? data.products : [];

      renderCategoryUI(categories, products);

      if (products.length === 0) {
        grid.innerHTML = '<p style="grid-column:1/-1; text-align:center; color:var(--text-muted); padding:3rem 0;">No products available right now. Please check back soon.</p>';
        return;
      }
      grid.innerHTML = products.map(cardHtml).join('');
    })
    .catch(function (err) {
      grid.innerHTML = '<p style="grid-column:1/-1; text-align:center; color:var(--text-muted); padding:3rem 0;">Could not load products right now. Please refresh the page or contact us on WhatsApp.</p>';
      console.error(err);
    });
})();
