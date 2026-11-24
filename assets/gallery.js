document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('gallery-download-form');
  if (!form) return;
  var boxes = Array.prototype.slice.call(form.querySelectorAll('input[name="photo_ids[]"]'));
  var all = document.getElementById('gallery-select-all');
  var button = document.getElementById('gallery-download-button');
  var count = document.getElementById('gallery-selected-count');
  function update() {
    var selected = boxes.filter(function (box) { return box.checked; }).length;
    if (count) count.textContent = selected + ' foto dipilih di halaman ini';
    if (button) button.disabled = selected === 0;
    if (all) {
      all.checked = boxes.length > 0 && selected === boxes.length;
      all.indeterminate = selected > 0 && selected < boxes.length;
    }
  }
  if (all) all.addEventListener('change', function () {
    boxes.forEach(function (box) { box.checked = all.checked; });
    update();
  });
  boxes.forEach(function (box) { box.addEventListener('change', update); });
  form.addEventListener('submit', function (event) {
    if (!boxes.some(function (box) { return box.checked; })) event.preventDefault();
  });
  update();
});
