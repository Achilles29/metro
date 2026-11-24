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

(() => {
  document.querySelectorAll('[data-modal-open]').forEach(button => {
    button.addEventListener('click', () => {
      const dialog = document.getElementById(button.dataset.modalOpen);
      if (dialog && !dialog.open) dialog.showModal();
    });
  });
  document.querySelectorAll('.admin-modal').forEach(dialog => {
    dialog.querySelectorAll('[data-modal-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
  });
  document.querySelectorAll('[data-tabs]').forEach(group => {
    const buttons = [...group.querySelectorAll('[data-tab]')];
    const panels = [...group.querySelectorAll('[data-tab-panel]')];
    if (!buttons.length || !panels.length) return;
    const select = (name, updateUrl = false) => {
      if (!buttons.some(button => button.dataset.tab === name)) return;
      buttons.forEach(button => {
        const active = button.dataset.tab === name;
        button.setAttribute('aria-selected', String(active));
        button.tabIndex = active ? 0 : -1;
      });
      panels.forEach(panel => { panel.hidden = panel.dataset.tabPanel !== name; });
      if (updateUrl) {
        const url = new URL(location.href);
        url.searchParams.set('tab', name);
        history.replaceState(null, '', url);
      }
      if (group.classList.contains('workflow-tabs') && matchMedia('(max-width: 680px)').matches
          && (updateUrl || new URL(location.href).searchParams.has('tab'))) {
        const panel = panels.find(item => item.dataset.tabPanel === name);
        const hashTarget = !updateUrl && location.hash ? document.getElementById(location.hash.slice(1)) : null;
        const anchor = hashTarget && panel?.contains(hashTarget) ? hashTarget : null;
        requestAnimationFrame(() => (anchor || panel)?.scrollIntoView({behavior: updateUrl ? 'smooth' : 'auto', block: 'start'}));
      }
    };
    const requested = new URL(location.href).searchParams.get('tab');
    select(buttons.some(button => button.dataset.tab === requested) ? requested : buttons[0].dataset.tab);
    buttons.forEach((button, index) => {
      button.addEventListener('click', () => select(button.dataset.tab, true));
      button.addEventListener('keydown', event => {
        if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        const next = buttons[(index + (event.key === 'ArrowRight' ? 1 : buttons.length - 1)) % buttons.length];
        next.focus(); select(next.dataset.tab, true);
      });
    });
  });
  document.querySelectorAll('[data-row-url]').forEach(row => {
    row.addEventListener('click', event => {
      if (!event.target.closest('a,button,input,select,textarea')) location.assign(row.dataset.rowUrl);
    });
    row.addEventListener('keydown', event => {
      if ((event.key === 'Enter' || event.key === ' ') && event.target === row) {
        event.preventDefault(); location.assign(row.dataset.rowUrl);
      }
    });
  });
})();
