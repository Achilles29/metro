(() => {
  const reviewForm = document.querySelector('[data-review-form]');
  if (reviewForm) {
    const criteria = [];
    reviewForm.querySelectorAll('.review-table tbody tr').forEach(row => {
      const suitable = row.querySelector('[data-review-suitable]');
      const trigger = row.querySelector('[data-review-open]');
      const summary = row.querySelector('[data-review-summary]');
      const dialog = trigger && document.getElementById(trigger.dataset.reviewOpen);
      if (!suitable || !trigger || !summary || !dialog) return;
      const choices = [...dialog.querySelectorAll('[data-review-unit]')];
      const error = dialog.querySelector('[data-review-error]');
      let snapshot = [];
      let restoreSuitable = false;
      let applied = false;
      const selected = () => choices.filter(choice => choice.checked);
      const render = () => {
        const count = selected().length;
        trigger.disabled = suitable.checked;
        choices.forEach(choice => {
          choice.disabled = suitable.checked;
          choice.closest('.review-unit-choice')?.classList.toggle('is-checked', choice.checked);
        });
        row.classList.toggle('review-unsuitable', !suitable.checked);
        summary.textContent = suitable.checked ? 'Semua UTTP sesuai' : count ? `${count} dari ${choices.length} UTTP dipilih` : 'Belum ada UTTP dipilih';
      };
      const open = (fromToggle = false) => {
        snapshot = choices.map(choice => choice.checked);
        restoreSuitable = fromToggle;
        applied = false;
        error.hidden = true;
        if (!dialog.open) dialog.showModal();
        dialog.querySelector('[data-review-unit]:checked, [data-review-unit]')?.focus();
      };
      suitable.addEventListener('change', () => {
        render();
        if (!suitable.checked) open(true);
      });
      trigger.addEventListener('click', () => open());
      choices.forEach(choice => choice.addEventListener('change', () => {
        error.hidden = selected().length > 0;
        render();
      }));
      dialog.querySelector('[data-review-select-all]')?.addEventListener('click', () => {
        choices.forEach(choice => { choice.checked = true; });
        error.hidden = true;
        render();
      });
      dialog.querySelector('[data-review-clear]')?.addEventListener('click', () => {
        choices.forEach(choice => { choice.checked = false; });
        render();
      });
      dialog.querySelector('[data-review-apply]')?.addEventListener('click', () => {
        if (!selected().length) {
          error.hidden = false;
          choices[0]?.focus();
          return;
        }
        applied = true;
        dialog.close();
      });
      dialog.addEventListener('close', () => {
        if (!applied) {
          choices.forEach((choice, index) => { choice.checked = snapshot[index]; });
          if (restoreSuitable) suitable.checked = true;
        }
        restoreSuitable = false;
        applied = false;
        render();
      });
      render();
      criteria.push({suitable, selected, open, error});
    });
    reviewForm.addEventListener('submit', event => {
      const missing = criteria.find(criterion => !criterion.suitable.checked && !criterion.selected().length);
      if (missing) {
        event.preventDefault();
        missing.open();
        missing.error.hidden = false;
        return;
      }
      if (!confirm('Simpan hasil kaji ulang untuk seluruh order?')) event.preventDefault();
    });
  }

  document.querySelectorAll('input[type=file]').forEach(input => {
    const note = document.createElement('small');
    note.className = 'selected-file';
    input.after(note);
    let url = null;
    input.addEventListener('change', () => {
      if (url) URL.revokeObjectURL(url);
      note.replaceChildren();
      const file = input.files?.[0];
      if (!file) return;
      note.append(document.createTextNode('Dipilih: ' + file.name + ' '));
      if (file.type === 'application/pdf' || file.type.startsWith('image/')) {
        url = URL.createObjectURL(file);
        const link = document.createElement('a');
        link.href = url; link.target = '_blank'; link.rel = 'noopener'; link.textContent = 'Preview';
        note.append(link);
      }
    });
  });
  const saved = new URL(location.href).searchParams.get('saved');
  if (saved) document.getElementById('unit-' + saved)?.setAttribute('open', '');
  const preview = document.getElementById('photo-preview');
  if (preview) {
    const img = preview.querySelector('[data-photo-image]');
    let scale = 1;
    const apply = () => { img.style.transform = `scale(${scale})`; };
    document.querySelectorAll('[data-photo-open]').forEach(button => button.addEventListener('click', () => {
      img.src = button.dataset.photoOpen;
      scale = 1; apply(); preview.showModal();
    }));
    preview.querySelector('[data-photo-close]')?.addEventListener('click', () => preview.close());
    preview.querySelectorAll('[data-photo-zoom]').forEach(button => button.addEventListener('click', () => {
      scale = Math.max(.5, Math.min(4, scale + (button.dataset.photoZoom === 'in' ? .25 : -.25)));
      apply();
    }));
    preview.addEventListener('click', event => { if (event.target === preview) preview.close(); });
    preview.addEventListener('close', () => { img.removeAttribute('src'); });
  }
})();
