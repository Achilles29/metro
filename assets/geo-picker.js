(() => {
  if (!window.L) return;
  const maps = new WeakMap();
  const defaultPoint = [-6.708,111.35];
  function point(picker) {
    const lat = Number(picker.querySelector('[data-geo-lat]')?.value);
    const lng = Number(picker.querySelector('[data-geo-lng]')?.value);
    return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180
      && picker.querySelector('[data-geo-lat]').value !== '' && picker.querySelector('[data-geo-lng]').value !== '' ? [lat,lng] : null;
  }
  function create(picker) {
    if (maps.has(picker)) {const entry = maps.get(picker);entry.map.invalidateSize();const current=point(picker);if (current) entry.map.setView(current,15);return;}
    const container = picker.querySelector('.geo-map');
    if (!container) return;
    container.hidden = false;
    const current = point(picker);
    const map = L.map(container,{scrollWheelZoom:false}).setView(current || defaultPoint,current ? 15 : 11);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{
      maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
    }).addTo(map);
    const marker = L.marker(current || defaultPoint,{draggable:true}).addTo(map);
    if (!current) marker.setOpacity(.55);
    const set = latlng => {
      picker.querySelector('[data-geo-lat]').value = latlng.lat.toFixed(7);
      picker.querySelector('[data-geo-lng]').value = latlng.lng.toFixed(7);
      const source = picker.querySelector('[data-geo-source]');if (source) source.value = 'map';
      marker.setLatLng(latlng).setOpacity(1);
    };
    map.on('click',event => set(event.latlng));
    marker.on('dragend',() => set(marker.getLatLng()));
    maps.set(picker,{map,marker});
    requestAnimationFrame(() => map.invalidateSize());
  }
  document.addEventListener('click',event => {
    const open = event.target.closest('[data-geo-open]');
    if (open) {const picker = open.closest('.geo-picker');create(picker);return;}
    const clear = event.target.closest('[data-geo-clear]');
    if (clear) {
      const picker = clear.closest('.geo-picker');
      picker.querySelector('[data-geo-lat]').value = '';
      picker.querySelector('[data-geo-lng]').value = '';
      const source = picker.querySelector('[data-geo-source]');if (source) source.value = '';
      const entry = maps.get(picker);if (entry) entry.marker.setOpacity(.55);
    }
  });
  document.addEventListener('change',event => {
    if (!event.target.matches('[data-geo-lat],[data-geo-lng]')) return;
    const picker = event.target.closest('.geo-picker');
    const source = picker.querySelector('[data-geo-source]');if (source) source.value = 'manual';
    const current = point(picker), entry = maps.get(picker);
    if (entry && current) {entry.marker.setLatLng(current).setOpacity(1);entry.map.panTo(current);}
  });
  document.addEventListener('toggle',event => {
    if (event.target.matches('dialog[open],details[open]')) event.target.querySelectorAll('.geo-picker').forEach(picker => {
      create(picker);
      requestAnimationFrame(() => maps.get(picker)?.map.invalidateSize());
    });
  },true);
  const visible=new IntersectionObserver(entries => entries.forEach(entry => {
    if (entry.isIntersecting) {create(entry.target);visible.unobserve(entry.target);}
  }),{rootMargin:'100px'});
  const watch=picker => {
    if (picker.getClientRects().length) create(picker);
    else visible.observe(picker);
  };
  const register=node => {
    if (node.nodeType!==1) return;
    if (node.matches?.('.geo-picker')) watch(node);
    node.querySelectorAll?.('.geo-picker').forEach(watch);
  };
  document.querySelectorAll('.geo-picker').forEach(register);
  new MutationObserver(records => records.forEach(record => {
    if (record.type==='attributes' && record.target.open)
      record.target.querySelectorAll('.geo-picker').forEach(watch);
    else if (record.type==='childList') record.addedNodes.forEach(register);
  })).observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['open']});
})();
