(() => {
  document.querySelectorAll('.ajax-village').forEach(picker => {
    const district = document.getElementById(picker.dataset.district);
    const hidden = picker.querySelector('input[type="hidden"]');
    const input = picker.querySelector('.ajax-village-input');
    const results = picker.querySelector('.ajax-village-results');
    let timer, controller, selectedIndex = -1;
    const close = () => { results.hidden = true; input.setAttribute('aria-expanded', 'false'); selectedIndex = -1; };
    const choose = (id, label) => { hidden.value = id; input.value = label; input.setCustomValidity(''); close(); };
    const render = rows => {
      results.replaceChildren(); selectedIndex = -1;
      if (!rows.length) {
        const empty = document.createElement('div'); empty.className = 'ajax-village-empty'; empty.textContent = 'Desa / kelurahan tidak ditemukan.'; results.appendChild(empty);
      } else rows.forEach(row => {
        const button = document.createElement('button'); button.type = 'button'; button.setAttribute('role', 'option'); button.className = 'ajax-village-option'; button.textContent = row.text;
        button.addEventListener('click', () => choose(row.id, row.text)); results.appendChild(button);
      });
      results.hidden = false; input.setAttribute('aria-expanded', 'true');
    };
    const search = async () => {
      if (!district?.value) { close(); return; }
      if (controller) controller.abort(); controller = new AbortController();
      const url = new URL(picker.dataset.url, location.href); url.searchParams.set('district_id', district.value); url.searchParams.set('q', input.value.trim());
      try {
        const response = await fetch(url, {signal:controller.signal, credentials:'same-origin', headers:{'Accept':'application/json'}});
        if (!response.ok) throw new Error('Gagal memuat desa');
        render((await response.json()).results || []);
      } catch (error) { if (error.name !== 'AbortError') { results.textContent = 'Daftar desa belum tersedia. Coba lagi.'; results.hidden = false; } }
    };
    district?.addEventListener('change', () => { hidden.value = ''; input.value = ''; input.disabled = !district.value; close(); });
    input.disabled = !district?.value;
    input.addEventListener('focus', () => { if (district?.value) search(); });
    input.addEventListener('input', () => { hidden.value = ''; clearTimeout(timer); timer = setTimeout(search, 180); });
    input.addEventListener('keydown', event => {
      const options = [...results.querySelectorAll('.ajax-village-option')];
      if (event.key === 'Escape') { close(); return; }
      if (!options.length || results.hidden) return;
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); selectedIndex = (selectedIndex + (event.key === 'ArrowDown' ? 1 : options.length - 1)) % options.length; options.forEach((o,i) => o.classList.toggle('focused',i === selectedIndex)); options[selectedIndex].scrollIntoView({block:'nearest'}); }
      if (event.key === 'Enter' && selectedIndex >= 0) { event.preventDefault(); options[selectedIndex].click(); }
    });
    document.addEventListener('click', event => { if (!picker.contains(event.target)) close(); });
    picker.closest('form')?.addEventListener('submit', event => { if (!hidden.value && (picker.dataset.optional !== '1' || district?.value || input.value.trim())) { event.preventDefault(); input.setCustomValidity('Pilih desa dari daftar pencarian.'); input.reportValidity(); } });
    input.addEventListener('input', () => input.setCustomValidity(''));
  });
})();
