(() => {
  if (!window.L) return;
  const maps=new WeakMap();
  function show(container) {
    if (maps.has(container)) {maps.get(container).invalidateSize();return;}
    let points=[];try {points=JSON.parse(container.dataset.points || '[]');} catch {}
    const map=L.map(container,{scrollWheelZoom:false}).setView([-6.708,111.35],11);
    maps.set(container,map);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{
      maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
    }).addTo(map);
    const bounds=[];
    points.forEach(p => {
      if (!Number.isFinite(+p.lat) || !Number.isFinite(+p.lng)) return;
      const marker=L.marker([+p.lat,+p.lng]).addTo(map);bounds.push([+p.lat,+p.lng]);
      const wrap=document.createElement('div');const title=document.createElement('strong');title.textContent=p.title || 'Titik';wrap.append(title);
      const detail=document.createElement('p');detail.textContent=(p.kind || '')+' · '+(p.detail || '');wrap.append(detail);
      if (p.url) {const a=document.createElement('a');a.href=p.url;a.textContent='Buka detail →';wrap.append(a);}
      marker.bindPopup(wrap);
    });
    if (bounds.length===1) map.setView(bounds[0],15);
    else if (bounds.length>1) map.fitBounds(bounds,{padding:[25,25],maxZoom:14});
    requestAnimationFrame(() => map.invalidateSize());
  }
  const visible=new IntersectionObserver(entries => entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    visible.unobserve(entry.target);
    show(entry.target);
  }),{rootMargin:'100px'});
  document.querySelectorAll('.geo-display').forEach(el => {
    if (el.getClientRects().length) show(el);
    else visible.observe(el);
  });
  document.addEventListener('click',event => {
    if (!event.target.closest('[data-tab]')) return;
    requestAnimationFrame(() => document.querySelectorAll('.geo-display').forEach(el => {
      if (el.getClientRects().length) show(el);
    }));
  });
})();
