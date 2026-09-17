/*
 * wp-portfolio case-study system: behaviour.
 *
 * Canonical copy: ~/.claude/skills/wp-portfolio/references/portfolio-starter/.
 * Everything here is an enhancement. With JavaScript off the page still reads
 * top to bottom, the carousel still scrolls, and every image is visible.
 */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- lightbox ---------- */

  function setupLightbox() {
    var dlg = document.getElementById('lightbox');
    if (!dlg || typeof dlg.showModal !== 'function') { return; }
    var body = document.getElementById('lightbox-body');
    var cap = document.getElementById('lightbox-cap');

    function captionFor(btn) {
      var fig = btn.closest('figure');
      var figcap = fig && fig.querySelector('figcaption');
      if (figcap) { return figcap.textContent.replace(/\s+/g, ' ').trim(); }
      return btn.getAttribute('aria-label') || '';
    }

    function dismiss() {
      dlg.close();
      body.replaceChildren();
    }

    document.querySelectorAll('.zoom').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var node;
        if (btn.dataset.svg) {
          node = btn.querySelector('svg').cloneNode(true);
          // A cloned SVG carries the original's ids, so its markers would
          // point back at the page copy. Renumber and re-point them.
          node.querySelectorAll('[id]').forEach(function (el) {
            var was = el.id;
            el.id = was + '-zoom';
            node.querySelectorAll('[marker-end="url(#' + was + ')"]').forEach(function (u) {
              u.setAttribute('marker-end', 'url(#' + was + '-zoom)');
            });
          });
        } else {
          var src = btn.querySelector('img');
          node = document.createElement('img');
          node.src = btn.dataset.full || src.getAttribute('src');
          node.alt = src.getAttribute('alt') || '';
        }
        body.replaceChildren(node);
        cap.textContent = captionFor(btn);
        dlg.showModal();
        body.scrollTop = 0;
        body.scrollLeft = 0;
      });
    });

    document.getElementById('lightbox-close').addEventListener('click', dismiss);
    // The dialog itself is the click target only when the press landed on
    // the backdrop.
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg) { dismiss(); }
    });
    // The close event does not fire reliably in every embedded viewer, so
    // cleanup also happens in the paths above.
    dlg.addEventListener('cancel', function () { body.replaceChildren(); });
  }

  /* ---------- evidence carousel ---------- */

  function setupCarousel(root) {
    var track = root.querySelector('.carousel__track');
    var slides = Array.prototype.slice.call(track.children);
    var prev = root.querySelector('[data-dir="-1"]');
    var next = root.querySelector('[data-dir="1"]');
    var count = root.querySelector('.carousel__count');
    var thumbs = Array.prototype.slice.call(root.querySelectorAll('.thumbs button'));
    var current = 0;

    function go(i) {
      i = Math.max(0, Math.min(slides.length - 1, i));
      track.scrollTo({ left: i * track.clientWidth, behavior: reduceMotion ? 'auto' : 'smooth' });
    }

    function sync() {
      var i = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
      if (i === current && count.textContent) { return; }
      current = i;
      count.textContent = (i + 1) + ' / ' + slides.length;
      prev.disabled = i === 0;
      next.disabled = i === slides.length - 1;
      thumbs.forEach(function (t, n) { t.setAttribute('aria-current', n === i ? 'true' : 'false'); });
    }

    prev.addEventListener('click', function () { go(current - 1); });
    next.addEventListener('click', function () { go(current + 1); });
    thumbs.forEach(function (t, n) { t.addEventListener('click', function () { go(n); }); });
    track.addEventListener('scroll', function () { window.requestAnimationFrame(sync); }, { passive: true });
    track.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); go(current + 1); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); go(current - 1); }
    });
    count.textContent = '';
    sync();
  }

  /* ---------- scroll motion (GSAP) ---------- */

  function setupMotion() {
    if (reduceMotion || !window.gsap || !window.ScrollTrigger) { return; }
    var gsap = window.gsap;
    gsap.registerPlugin(window.ScrollTrigger);

    // Image scale: evidence grows into place as it arrives, then dims as it
    // leaves the top. It starts fully opaque, so a still frame shows it.
    gsap.utils.toArray('[data-scale]').forEach(function (el) {
      gsap.fromTo(el, { scale: 0.92 }, {
        scale: 1,
        ease: 'none',
        scrollTrigger: { trigger: el, start: 'top bottom', end: 'top 55%', scrub: 0.6 }
      });
      gsap.to(el, {
        opacity: 0.45,
        ease: 'none',
        scrollTrigger: { trigger: el, start: 'bottom 30%', end: 'bottom top', scrub: 0.6 }
      });
    });

    // Card stacking: each revision card is sticky (CSS); the one underneath
    // settles back as the next slides over it.
    gsap.matchMedia().add('(min-width: 760px) and (min-height: 640px)', function () {
      var cards = gsap.utils.toArray('.stack-card');
      cards.forEach(function (card, i) {
        var nextCard = cards[i + 1];
        if (!nextCard) { return; }
        gsap.to(card, {
          scale: 0.95,
          ease: 'none',
          scrollTrigger: { trigger: nextCard, start: 'top bottom', end: 'top 30%', scrub: 0.6 }
        });
      });
    });
  }

  function init() {
    setupLightbox();
    document.querySelectorAll('.carousel').forEach(setupCarousel);
    setupMotion();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
