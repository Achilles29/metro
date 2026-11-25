(() => {
  function update(form) {
    const type=form.querySelector('[name="uttp_type_id"]');
    const pubbm=type?.selectedOptions[0]?.dataset.pubbm==='1';
    form.querySelectorAll('[data-pubbm-field]').forEach(el=>el.hidden=!pubbm);
    form.querySelectorAll('[data-other-field]').forEach(el=>el.hidden=pubbm);
  }
  document.querySelectorAll('form:has([name="uttp_type_id"])').forEach(form=>{
    form.querySelector('[name="uttp_type_id"]')?.addEventListener('change',()=>update(form));
    update(form);
  });
})();
