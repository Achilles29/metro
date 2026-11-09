(() => {
  const list = document.getElementById('uttp-list');
  const template = document.getElementById('uttp-template');
  const add = () => {
    if (list.children.length >= 20) return;
    list.appendChild(template.content.cloneNode(true));
    update();
  };
  const update = () => {
    [...list.children].forEach((card, index) => {
      card.querySelector('.uttp-number').textContent = index + 1;
      card.querySelector('.remove-uttp').disabled = list.children.length === 1;
    });
  };
  document.getElementById('add-uttp').addEventListener('click', add);
  list.addEventListener('click', event => {
    if (event.target.classList.contains('remove-uttp') && list.children.length > 1) {
      event.target.closest('.uttp-card').remove(); update();
    }
  });
  add();
})();
