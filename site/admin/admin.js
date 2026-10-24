/* Frisbee CMS — admin interactions (no libraries) */
(() => {
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];

  // Confirm destructive actions
  document.addEventListener('submit', e => {
    const msg = e.target.dataset.confirm;
    if (msg && !confirm(msg)) e.preventDefault();
  }, true);

  // Repeaters: add, remove, move rows
  let n = 0;
  document.addEventListener('click', e => {
    const add = e.target.closest('[data-add]');
    if (add) {
      const rep = add.closest('.repeater');
      const key = '__n' + Date.now().toString(36) + (n++) + '__';
      const html = rep.querySelector('template').innerHTML.split('__KEY__').join(key);
      rep.querySelector('.rep-list').insertAdjacentHTML('beforeend', html);
      const row = rep.querySelector('.rep-list').lastElementChild;
      bind(row);
      row.querySelector('input,textarea,select')?.focus();
      dirty(rep.closest('form'));
      return;
    }
    const rm = e.target.closest('[data-remove]');
    if (rm) { const f = rm.closest('form'); rm.closest('.rep-row').remove(); dirty(f); return; }
    const mv = e.target.closest('[data-move]');
    if (mv) {
      const row = mv.closest('.rep-row');
      if (mv.dataset.move === '-1' && row.previousElementSibling) row.previousElementSibling.before(row);
      if (mv.dataset.move === '1' && row.nextElementSibling) row.nextElementSibling.after(row);
      dirty(row.closest('form')); mv.focus(); return;
    }
    // Gallery items
    const gm = e.target.closest('[data-gmove]');
    if (gm) {
      const it = gm.closest('.gal-item');
      if (gm.dataset.gmove === '-1' && it.previousElementSibling) it.previousElementSibling.before(it);
      if (gm.dataset.gmove === '1' && it.nextElementSibling) it.nextElementSibling.after(it);
      dirty(it.closest('form')); gm.focus(); return;
    }
    const gr = e.target.closest('[data-gremove]');
    if (gr) { const f = gr.closest('form'); gr.closest('.gal-item').remove(); dirty(f); }
  });

  // Colour picker <-> hex text field, image previews
  function bind(root) {
    $$('input[type=color][data-sync]', root).forEach(picker => {
      const text = document.getElementById(picker.dataset.sync);
      picker.addEventListener('input', () => { text.value = picker.value; dirty(picker.closest('form')); });
      text.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value; });
    });
    $$('select[data-img-select]', root).forEach(sel => sel.addEventListener('change', () => {
      const box = document.querySelector(`[data-preview="${sel.id}"]`);
      box.innerHTML = sel.value ? `<img src="../${sel.value}" alt="">` : '<span>Geen beeld</span>';
    }));
    $$('input[type=file][data-img-upload]', root).forEach(inp => inp.addEventListener('change', () => {
      const box = document.querySelector(`[data-preview="${inp.dataset.imgUpload}"]`);
      const file = inp.files[0];
      if (file && box) { const url = URL.createObjectURL(file); box.innerHTML = ''; const img = new Image(); img.src = url; img.alt = ''; box.append(img); }
    }));
  }
  bind(document);

  // Warn before leaving with unsaved changes
  const dirty = f => f && f.matches('[data-dirty-guard]') && f.classList.add('dirty');
  $$('form[data-dirty-guard]').forEach(f => {
    f.addEventListener('input', () => dirty(f));
    f.addEventListener('change', () => dirty(f));
    f.addEventListener('submit', () => f.classList.remove('dirty'));
  });
  addEventListener('beforeunload', e => { if (document.querySelector('form.dirty')) { e.preventDefault(); e.returnValue = ''; } });
})();
