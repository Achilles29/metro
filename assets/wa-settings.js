(() => {
  const script = document.currentScript;
  const url = script?.dataset.qrUrl;
  const target = document.getElementById('wa-qr');
  if (!url || !target) return;
  const message = document.getElementById('wa-qr-message');
  const status = document.getElementById('wa-status');
  const phone = document.getElementById('wa-phone');
  async function refresh() {
    try {
      const response = await fetch(url,{credentials:'same-origin',cache:'no-store'});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Bot tidak merespons.');
      status.textContent = data.status;
      phone.textContent = data.phone || '—';
      target.replaceChildren();
      if (data.qr && window.QRCode) {
        new QRCode(target,{text:data.qr,width:218,height:218,correctLevel:QRCode.CorrectLevel.M});
        message.textContent = 'Scan QR melalui WhatsApp → Perangkat tertaut → Tautkan perangkat.';
      } else if (data.status === 'CONNECTED') message.textContent = 'Nomor sudah terhubung. Kode verifikasi otomatis dapat dikirim.';
      else if (data.status === 'DISABLED') message.textContent = 'Aktifkan bot untuk menampilkan QR.';
      else message.textContent = 'QR sedang disiapkan. Muat ulang beberapa saat lagi.';
    } catch (error) {message.textContent = error.message;}
  }
  document.getElementById('wa-refresh')?.addEventListener('click',refresh);
  refresh();
  setInterval(refresh,20000);
})();
