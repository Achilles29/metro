(() => {
  const target = location.hash && document.getElementById(location.hash.slice(1));
  if (target?.tagName === 'DETAILS') target.open = true;
  const updateCsrf = data => {
    if (data?.csrf_name) document.querySelectorAll(`input[name="${data.csrf_name}"]`).forEach(input => { input.value = data.csrf_hash; });
  };
  document.querySelectorAll('[data-auto-upload]').forEach(form => {
    const file = form.querySelector('input[type="file"]');
    const number = form.querySelector('[name="certificate_no"]');
    const result = form.querySelector('.upload-result');
    const bar = form.querySelector('progress');
    file?.addEventListener('change', () => {
      if (!file.files?.length) return;
      if (!number.checkValidity()) { number.reportValidity(); file.value = ''; return; }
      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.action);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.upload.onprogress = event => { if (event.lengthComputable) bar.value = Math.round(event.loaded / event.total * 100); };
      xhr.onload = () => {
        let data; try { data = JSON.parse(xhr.responseText); } catch (_) { data = null; }
        updateCsrf(data);
        if (xhr.status < 200 || xhr.status >= 300 || !data?.ok) {
          result.textContent = data?.message || 'Unggah gagal. Periksa berkas dan coba lagi.';
          file.disabled = false; number.disabled = false; bar.hidden = true; file.value = '';
          return;
        }
        result.textContent = 'SKHP tersimpan. Memuat ulang daftar dokumen…';
        location.reload();
      };
      xhr.onerror = () => { result.textContent = 'Koneksi terputus. Coba lagi.'; file.disabled = false; number.disabled = false; bar.hidden = true; };
      const payload = new FormData(form);
      result.textContent = 'Mengunggah ' + file.files[0].name + '…';
      bar.value = 0; bar.hidden = false; file.disabled = true; number.disabled = true;
      xhr.send(payload);
    });
  });
})();
