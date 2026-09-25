/* IR-Jalali builder frontend runtime: carousel, counters, entrance animations. */
(function () {
  'use strict';

  // Entrance animations.
  var animated = document.querySelectorAll('.ij-anim');
  if ('IntersectionObserver' in window && animated.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var el = entry.target;
          el.style.setProperty('--ij-delay', (el.getAttribute('data-ij-delay') || 0) + 'ms');
          el.style.setProperty('--ij-dur', (el.getAttribute('data-ij-duration') || 600) + 'ms');
          el.classList.add('ij-in');
          io.unobserve(el);
        }
      });
    }, { threshold: 0.12 });
    animated.forEach(function (el) { io.observe(el); });
  } else {
    animated.forEach(function (el) { el.classList.add('ij-in'); });
  }

  // Carousels / sliders.
  document.querySelectorAll('.ij-carousel').forEach(function (car) {
    var track = car.querySelector('.ij-track');
    var slides = car.querySelectorAll('.ij-slide');
    if (!track || slides.length < 2) return;
    var index = 0;
    var rtl = document.dir === 'rtl';
    function go(i) {
      index = (i + slides.length) % slides.length;
      var x = rtl ? (index * 100) : (-index * 100);
      track.style.transform = 'translateX(' + x + '%)';
    }
    var prev = car.querySelector('.ij-prev');
    var next = car.querySelector('.ij-next');
    if (prev) prev.addEventListener('click', function () { go(index - 1); restart(); });
    if (next) next.addEventListener('click', function () { go(index + 1); restart(); });
    var timer = null;
    var interval = parseInt(car.getAttribute('data-autoplay') || '0', 10);
    function restart() {
      if (timer) clearInterval(timer);
      if (interval > 0) timer = setInterval(function () { go(index + 1); }, interval);
    }
    restart();
  });

  // Animated counters.
  var counters = document.querySelectorAll('.ij-num[data-count]');
  if ('IntersectionObserver' in window && counters.length) {
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        cio.unobserve(el);
        var target = parseInt(el.getAttribute('data-count') || '0', 10);
        var start = null;
        function tick(ts) {
          if (!start) start = ts;
          var p = Math.min(1, (ts - start) / 1400);
          var eased = 1 - Math.pow(1 - p, 3);
          el.textContent = Math.round(target * eased).toLocaleString('en-US');
          if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
      });
    }, { threshold: 0.4 });
    counters.forEach(function (el) { cio.observe(el); });
  }
})();
