/* =====================================================
   SKYPOINT — Tiny image lightbox
   Click any <img data-lightbox> to view it full size.
   - data-lightbox="url"  → full-size source (optional; defaults to img src)
   - data-caption="text"  → caption (optional; defaults to alt)
   Close with the × button, a click on the backdrop, or Esc.
   Self-contained (injects its own styles) so it works on both the public
   site and the admin panel. Uses event delegation, so images added later
   (e.g. product cards rendered from products.json) work automatically.
   ===================================================== */
(function () {
  if (window.__skypointLightbox) return;
  window.__skypointLightbox = true;

  var css =
    '[data-lightbox]{cursor:zoom-in}' +
    '.slb{position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;' +
      'flex-direction:column;gap:12px;padding:56px 16px 24px;background:rgba(10,12,18,0.88);' +
      '-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px);opacity:0;transition:opacity .18s ease}' +
    '.slb.open{display:flex}.slb.show{opacity:1}' +
    '.slb img{max-width:min(1100px,100%);max-height:calc(100vh - 140px);object-fit:contain;border-radius:10px;' +
      'background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.5);cursor:zoom-out}' +
    '.slb-cap{color:#f1f1f1;font:500 14px/1.4 Inter,system-ui,sans-serif;text-align:center;max-width:90vw}' +
    '.slb-close{position:absolute;top:12px;right:12px;width:44px;height:44px;border-radius:50%;border:0;' +
      'background:rgba(255,255,255,.14);color:#fff;font-size:28px;line-height:44px;cursor:pointer}' +
    '.slb-close:hover,.slb-close:focus-visible{background:rgba(255,255,255,.28);outline:none}' +
    '.slb-remove{position:absolute;top:14px;left:14px;display:none;align-items:center;gap:6px;height:40px;padding:0 14px;' +
      'border-radius:20px;border:0;background:#dc2626;color:#fff;font:600 14px Inter,system-ui,sans-serif;cursor:pointer}' +
    '.slb-remove:hover,.slb-remove:focus-visible{background:#b91c1c;outline:none}' +
    '.slb-remove span{font-size:20px;line-height:1}';
  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  var box = document.createElement('div');
  box.className = 'slb';
  box.setAttribute('role', 'dialog');
  box.setAttribute('aria-modal', 'true');
  box.setAttribute('aria-label', 'Photo viewer');
  box.innerHTML = '<button type="button" class="slb-close" aria-label="Close">&times;</button>' +
    '<button type="button" class="slb-remove" title="Remove this photo"><span>&times;</span> Remove photo</button>' +
    '<img alt=""><div class="slb-cap"></div>';
  var img = box.querySelector('img');
  var cap = box.querySelector('.slb-cap');
  var closeBtn = box.querySelector('.slb-close');
  var removeBtn = box.querySelector('.slb-remove');
  var removeTarget = null;
  var lastFocus = null;

  function attach() { if (!box.parentNode) document.body.appendChild(box); }

  // removeId: id of a button on the page that removes this photo (optional).
  function open(src, caption, removeId) {
    removeTarget = removeId ? document.getElementById(removeId) : null;
    removeBtn.style.display = removeTarget ? 'flex' : 'none';
    attach();
    lastFocus = document.activeElement;
    img.src = src;
    img.alt = caption || '';
    cap.textContent = caption || '';
    cap.style.display = caption ? '' : 'none';
    box.classList.add('open');
    document.documentElement.style.overflow = 'hidden';
    requestAnimationFrame(function () { box.classList.add('show'); });
    closeBtn.focus();
  }

  function close() {
    if (!box.classList.contains('open')) return;
    box.classList.remove('show', 'open');
    document.documentElement.style.overflow = '';
    img.removeAttribute('src');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('img[data-lightbox]') : null;
    if (t && !box.contains(t)) {
      if (!t.getAttribute('src')) return;
      e.preventDefault();
      e.stopPropagation();
      open(t.getAttribute('data-lightbox') || t.currentSrc || t.src, t.getAttribute('data-caption') || t.alt,
           t.getAttribute('data-lightbox-remove'));
      return;
    }
    if (box.classList.contains('open') && removeTarget && removeBtn.contains(e.target)) {
      var target = removeTarget;
      close();
      target.click();
      return;
    }
    if (box.classList.contains('open') && (e.target === box || e.target === img || e.target === closeBtn)) close();
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
    // Keyboard users: Enter/Space on a focused zoomable image opens it.
    if ((e.key === 'Enter' || e.key === ' ') && document.activeElement &&
        document.activeElement.matches && document.activeElement.matches('img[data-lightbox]')) {
      e.preventDefault();
      document.activeElement.click();
    }
  });
})();
