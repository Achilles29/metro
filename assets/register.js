(() => {
  const select = document.getElementById('account-kind');
  if (!select) return;
  const update = () => {
    const technician = select.value === 'technician';
    document.querySelectorAll('[data-applicant-field]').forEach(el => {el.hidden = technician;el.querySelectorAll('input,select').forEach(input => input.disabled = technician);});
    document.querySelectorAll('[data-technician-field]').forEach(el => {el.hidden = !technician;el.querySelectorAll('input,select').forEach(input => input.disabled = !technician);});
  };
  select.addEventListener('change',update);update();
})();
