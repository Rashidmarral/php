(function () {
  var targets = document.querySelectorAll('.reveal, .reveal-section');
  if (!targets.length) return;

  if (!('IntersectionObserver' in window)) {
    targets.forEach(function (el) { el.classList.add('in-view'); });
    return;
  }

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('in-view');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

  targets.forEach(function (el) { observer.observe(el); });
})();

/* ---------- 3D tilt on cards (pricing, feature, testimonial) ---------- */
(function () {
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (!window.matchMedia || !window.matchMedia('(hover: hover)').matches) return;

  var cards = document.querySelectorAll('.pricing-card, .feature-card, .testi-card');
  cards.forEach(function (card) {
    card.style.transformStyle = 'preserve-3d';
    card.style.willChange = 'transform';

    card.addEventListener('mousemove', function (e) {
      var rect = card.getBoundingClientRect();
      var x = (e.clientX - rect.left) / rect.width;
      var y = (e.clientY - rect.top) / rect.height;
      var rotateY = (x - 0.5) * 10;
      var rotateX = (0.5 - y) * 10;
      card.style.transform = 'perspective(900px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) translateY(-4px) scale(1.015)';
    });

    card.addEventListener('mouseleave', function () {
      card.style.transform = '';
    });
  });
})();

/* ---------- Hero floating card parallax ---------- */
(function () {
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (!window.matchMedia || !window.matchMedia('(hover: hover)').matches) return;

  var hero = document.querySelector('.hero');
  var visual = hero ? hero.querySelector('.hero-visual') : null;
  if (!hero || !visual) return;

  visual.style.transformStyle = 'preserve-3d';
  hero.addEventListener('mousemove', function (e) {
    var rect = hero.getBoundingClientRect();
    var x = (e.clientX - rect.left) / rect.width;
    var y = (e.clientY - rect.top) / rect.height;
    var rotateY = (x - 0.5) * 8;
    var rotateX = (0.5 - y) * 8;
    visual.style.transform = 'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
  });
  hero.addEventListener('mouseleave', function () {
    visual.style.transform = '';
  });
})();

/* ---------- Count-up numbers when scrolled into view ---------- */
(function () {
  var els = document.querySelectorAll('.count-up');
  if (!els.length) return;

  function animate(el) {
    var raw = el.textContent.trim();
    var match = raw.match(/^([^\d]*)([\d,]+)(.*)$/);
    if (!match) return;
    var prefix = match[1], target = parseInt(match[2].replace(/,/g, ''), 10), suffix = match[3];
    if (isNaN(target)) return;
    var start = null, duration = 1100;
    function step(ts) {
      if (!start) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var value = Math.round(target * eased);
      el.textContent = prefix + value.toLocaleString('en-US') + suffix;
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  if (!('IntersectionObserver' in window) || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
    return;
  }
  var counterObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        animate(entry.target);
        counterObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.4 });
  els.forEach(function (el) { counterObserver.observe(el); });
})();
