/* Frisbee Agency — interactions for homepage and case pages */
(() => {
  const $ = s => document.querySelector(s);
  const $$ = s => [...document.querySelectorAll(s)];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fine = matchMedia('(hover:hover)').matches && !reduce;

  // Sticky nav: glass background after scrolling
  const nav = $('#nav');
  const onScroll = () => nav && nav.classList.toggle('scrolled', scrollY > 10);
  addEventListener('scroll', onScroll, {passive: true}); onScroll();

  // Mobile menu
  const burger = $('#burger');
  if (burger) {
    burger.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      burger.setAttribute('aria-expanded', open);
      burger.setAttribute('aria-label', open ? 'Menu sluiten' : 'Menu openen');
    });
    $$('#menu a').forEach(a => a.addEventListener('click', () => { nav.classList.remove('open'); burger.setAttribute('aria-expanded', false); }));
  }

  // Helper: write pointer position into CSS variables (-0.5 … 0.5)
  const track = (el, onMove, onLeave) => {
    el.addEventListener('pointermove', e => { const r = el.getBoundingClientRect(); onMove((e.clientX - r.left) / r.width - .5, (e.clientY - r.top) / r.height - .5, e, r); });
    el.addEventListener('pointerleave', onLeave);
  };

  // Hero: spotlight + disc tilt
  const stage = $('#stage'), disc = $('#disc');
  if (stage && disc && fine) track(stage, (x, y) => {
    stage.style.setProperty('--mx', (x + .5) * 100 + '%'); stage.style.setProperty('--my', (y + .5) * 100 + '%');
    disc.style.setProperty('--ry', x * 40 + 'deg'); disc.style.setProperty('--rx', -y * 30 + 8 + 'deg');
  }, () => { disc.style.removeProperty('--rx'); disc.style.removeProperty('--ry'); });

  // Service cards: glow follows the cursor
  $$('.svc').forEach(c => c.addEventListener('pointermove', e => {
    const r = c.getBoundingClientRect();
    c.style.setProperty('--gx', e.clientX - r.left + 'px'); c.style.setProperty('--gy', e.clientY - r.top + 'px');
  }));

  // Project tiles + case hero frame: tilt and parallax
  if (fine) $$('.proj, #frame').forEach(p => track(p,
    (x, y) => { p.style.setProperty('--tx', x.toFixed(3)); p.style.setProperty('--ty', y.toFixed(3)); },
    () => { p.style.removeProperty('--tx'); p.style.removeProperty('--ty'); }));

  // Magnetic buttons
  if (fine) $$('.btn').forEach(b => track(b,
    (x, y, e, r) => { b.style.transform = `translate(${x * r.width * .18}px, ${y * r.height * .3}px)`; },
    () => { b.style.transform = ''; }));

  // Process: draw the flight path when visible
  const steps = $('#steps');
  if (steps) {
    if ('IntersectionObserver' in window) new IntersectionObserver((es, o) => es.forEach(e => { if (e.isIntersecting) { steps.classList.add('go'); o.disconnect(); } }), {threshold: .3}).observe(steps);
    else steps.classList.add('go');
  }

  // Toast + copy helpers
  const toast = $('#toast');
  const say = t => { if (!toast) return; toast.textContent = t; toast.classList.add('show'); clearTimeout(say.t); say.t = setTimeout(() => toast.classList.remove('show'), 2200); };
  const copy = (text, msg) => (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(() => say(msg), () => say(text));
  const copyBtn = $('#copy');
  if (copyBtn) copyBtn.addEventListener('click', () => copy($('#mail').textContent.trim(), 'E-mailadres gekopieerd'));
  $$('.swatch').forEach(s => s.addEventListener('click', () => copy(s.dataset.hex, 'Kleur gekopieerd: ' + s.dataset.hex)));

  // Contact form: send in the background, show errors next to the fields
  const form = $('#cform');
  if (form && window.fetch && window.FormData) {
    const msg = $('#cf-msg'), btn = form.querySelector('button[type=submit]');
    const clear = () => { form.querySelectorAll('[data-err]').forEach(e => { e.textContent = ''; e.closest('.cf')?.classList.remove('bad'); }); msg.textContent = ''; msg.classList.remove('err'); };
    form.addEventListener('submit', async e => {
      e.preventDefault();
      clear();
      btn.disabled = true;
      msg.textContent = 'Bezig met versturen…';
      try {
        const r = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {Accept: 'application/json'}});
        const res = await r.json();
        if (res.ok) {
          form.classList.add('sent');
          msg.textContent = res.melding;
          form.scrollIntoView({behavior: reduce ? 'auto' : 'smooth', block: 'center'});
          return;
        }
        Object.entries(res.fouten || {}).forEach(([k, v]) => {
          const el = form.querySelector(`[data-err="${k}"]`);
          if (el) { el.textContent = v; el.closest('.cf')?.classList.add('bad'); }
        });
        if (res.captcha) { $('#cf-q').textContent = res.captcha.vraag; $('#cf-token').value = res.captcha.token; $('#cf-captcha').value = ''; }
        if (window.turnstile) window.turnstile.reset();
        msg.textContent = res.melding; msg.classList.add('err');
        form.querySelector('.cf.bad input, .cf.bad textarea')?.focus();
      } catch {
        msg.textContent = 'Versturen lukte niet. Controleer je internetverbinding en probeer opnieuw.'; msg.classList.add('err');
      } finally { btn.disabled = false; }
    });
  }

  // Case gallery lightbox
  const lb = $('#lb');
  const shots = $$('.shot');
  if (lb && shots.length) {
    const img = $('#lbImg'), count = $('#lbCount');
    const list = shots.map(s => s.dataset.src);
    let at = 0, last = null;
    const show = () => { img.src = list[at]; count.textContent = `${at + 1} / ${list.length}`; };
    const open = n => { at = n; last = document.activeElement; show(); lb.classList.add('open'); lb.removeAttribute('aria-hidden'); $('#lbClose').focus(); };
    const close = () => { lb.classList.remove('open'); lb.setAttribute('aria-hidden', 'true'); last && last.focus(); };
    const step = d => { at = (at + d + list.length) % list.length; show(); };
    shots.forEach((s, i) => s.addEventListener('click', () => open(i)));
    $('#lbClose').addEventListener('click', close);
    $('#lbPrev').addEventListener('click', () => step(-1));
    $('#lbNext').addEventListener('click', () => step(1));
    lb.addEventListener('click', e => { if (e.target === lb) close(); });
    addEventListener('keydown', e => {
      if (!lb.classList.contains('open')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') step(-1);
      if (e.key === 'ArrowRight') step(1);
    });
    if (list.length < 2) { $('#lbPrev').hidden = true; $('#lbNext').hidden = true; }
  }
})();
