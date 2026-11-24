document.querySelectorAll('input[type="password"]').forEach(input => {
  const wrap = document.createElement('span'); wrap.className = 'password-wrap';
  input.parentNode.insertBefore(wrap, input); wrap.appendChild(input);
  const toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'password-toggle';
  toggle.textContent = 'Lihat'; toggle.setAttribute('aria-label', 'Tampilkan kata sandi');
  toggle.addEventListener('click', () => { const showing = input.type === 'text'; input.type = showing ? 'password' : 'text'; toggle.textContent = showing ? 'Lihat' : 'Sembunyikan'; toggle.setAttribute('aria-label', showing ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi'); });
  wrap.appendChild(toggle);
});
