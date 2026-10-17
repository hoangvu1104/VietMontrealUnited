document.querySelector('.menu-toggle')?.addEventListener('click', function () {
  const open = document.body.classList.toggle('nav-open');
  this.setAttribute('aria-expanded', String(open));
});
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    document.body.classList.remove('nav-open');
    document.querySelector('.menu-toggle')?.setAttribute('aria-expanded', 'false');
  }
});
document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
