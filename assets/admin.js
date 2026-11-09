(() => {
  const body = document.body;
  const toggle = document.querySelector('[data-sidebar-toggle]');
  const close = document.querySelector('[data-sidebar-close]');
  if (!toggle || !close) return;
  const setOpen = open => {
    body.classList.toggle('sidebar-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
  };
  toggle.addEventListener('click', () => setOpen(!body.classList.contains('sidebar-open')));
  close.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') setOpen(false); });
  document.querySelectorAll('.admin-sidebar a').forEach(link => link.addEventListener('click', () => setOpen(false)));
})();
