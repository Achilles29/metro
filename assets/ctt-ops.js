(() => {
  document.querySelectorAll('.ctt-ops-modal').forEach(dialog => {
    const search = dialog.querySelector('[data-ctt-search]');
    if (!search) return;
    const rows = [...dialog.querySelectorAll('.ctt-ops-table tbody tr')];
    const count = dialog.querySelector('[data-ctt-count]');
    const info = dialog.querySelector('[data-ctt-page-info]');
    const prev = dialog.querySelector('[data-ctt-prev]');
    const next = dialog.querySelector('[data-ctt-next]');
    const pageSize = 10;
    let page = 1;
    const render = () => {
      const query = search.value.trim().toLocaleLowerCase('id');
      const filtered = rows.filter(row => row.textContent.toLocaleLowerCase('id').includes(query));
      const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
      page = Math.min(page, totalPages);
      rows.forEach(row => { row.hidden = true; });
      filtered.slice((page - 1) * pageSize, page * pageSize).forEach(row => { row.hidden = false; });
      count.textContent = `${filtered.length} order`;
      info.textContent = filtered.length ? `Halaman ${page} dari ${totalPages}` : 'Tidak ada order yang cocok';
      prev.disabled = page <= 1;
      next.disabled = page >= totalPages;
    };
    search.addEventListener('input', () => { page = 1; render(); });
    prev.addEventListener('click', () => { page--; render(); });
    next.addEventListener('click', () => { page++; render(); });
    render();
  });
})();
