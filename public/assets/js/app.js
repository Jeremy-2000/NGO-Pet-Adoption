document.addEventListener('DOMContentLoaded', () => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    carousel.dataset.index = carousel.dataset.index || '0';
    const slides = carousel.querySelector('.slides');
    const total = carousel.querySelectorAll('.slide').length;

    const move = (direction) => {
      if (!slides || total < 2) return;
      const current = Number.parseInt(carousel.dataset.index || '0', 10);
      const next = (current + direction + total) % total;
      carousel.dataset.index = String(next);
      slides.style.transform = `translateX(-${next * 100}%)`;
    };

    carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => move(-1));
    carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => move(1));
  });

  if (reduceMotion) return;

  document.querySelectorAll('[data-animate]').forEach((element, index) => {
    element.animate([
      { opacity: 0, transform: 'translateY(10px)' },
      { opacity: 1, transform: 'translateY(0)' }
    ], {
      duration: 240,
      delay: index * 45,
      fill: 'both',
      easing: 'ease-out'
    });
  });
});
