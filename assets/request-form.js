(() => {
  const list = document.getElementById('uttp-list');
  if (!list) return;
  const template = document.getElementById('uttp-template');
  const company = document.getElementById('request-company');
  company?.addEventListener('change', () => {
    const url = new URL(location.href);url.searchParams.set('company_id',company.value);location.assign(url.href);
  });
  const mode = document.querySelector('[name="service_mode"]');
  const address = document.querySelector('[name="service_address"]');
  const updateAddress = () => {
    const fixed = mode.selectedOptions[0]?.dataset.address || '';
    address.value = fixed || address.dataset.fieldAddress;
    address.readOnly = !!fixed;
  };
  mode.addEventListener('change', updateAddress);
  updateAddress();
  const updateCapacity = card => {
    const select = card.querySelector('[name="uttp_type_id[]"]');
    const mass = select.selectedOptions[0]?.dataset.mass === '1';
    const field = card.querySelector('.capacity-field');
    field.hidden = !mass;
    if (!mass) field.querySelector('input').value = '';
  };
  const update = () => {
    [...list.children].forEach((card, index) => {
      card.querySelector('.uttp-number').textContent = index + 1;
      card.querySelector('.remove-uttp').disabled = list.children.length === 1;
      updateCapacity(card);
    });
  };
  const add = () => {
    if (list.children.length >= 20) return;
    list.appendChild(template.content.cloneNode(true));
    update();
  };
  document.getElementById('add-uttp').addEventListener('click', add);
  list.addEventListener('change', event => {
    const card=event.target.closest('.uttp-card');
    if (event.target.matches('.saved-uttp')) {
      const data=event.target.selectedOptions[0]?.dataset;
      if (event.target.value && data) {
        card.querySelector('[name="uttp_type_id[]"]').value=data.type || '';
        card.querySelector('[name="brand[]"]').value=data.brand || '';
        card.querySelector('[name="type_name[]"]').value=data.typeName || '';
        card.querySelector('[name="capacity[]"]').value=data.capacity || '';
        card.querySelector('[name="serial_number[]"]').value=data.serial || '';
        card.querySelector('[name="item_address[]"]').value=data.address || '';
        card.querySelector('[name="item_latitude[]"]').value=data.lat || '';
        card.querySelector('[name="item_longitude[]"]').value=data.lng || '';
        card.querySelector('[name="item_location_source[]"]').value=data.source || '';
      }
      updateCapacity(card);
    }
    if (event.target.name === 'uttp_type_id[]') {
      const saved=card.querySelector('.saved-uttp');
      if (saved.value && saved.selectedOptions[0]?.dataset.type!==event.target.value) saved.value='';
      updateCapacity(card);
    }
  });
  list.addEventListener('click', event => {
    if (event.target.classList.contains('remove-uttp') && list.children.length > 1) {
      event.target.closest('.uttp-card').remove(); update();
    }
  });
  add();
})();
