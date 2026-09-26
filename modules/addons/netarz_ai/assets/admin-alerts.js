/*! NetArz AI for WHMCS — "a visitor is waiting" toast on every admin page. */
(function () {
  'use strict';

  var script = document.currentScript || document.querySelector('script[data-netarz-alerts]');
  if (!script || window.__netarzAlerts) return;
  window.__netarzAlerts = true;

  // The inbox page shows the same information already.
  if (/[?&]module=netarz_ai(&|$)/.test(location.search) && /[?&]tab=inbox/.test(location.search)) return;

  var ENDPOINT = script.getAttribute('data-endpoint');
  var INBOX = script.getAttribute('data-inbox');
  var TITLE = script.getAttribute('data-title');
  var OPEN = script.getAttribute('data-open');
  var RTL = script.getAttribute('data-rtl') === '1';
  var KEY = 'netarz_ai_alert_seen';
  var since = 0;
  try { since = +(sessionStorage.getItem(KEY) || 0); } catch (e) {}
  var first = true;
  var toast = null;

  function show(data) {
    if (toast) toast.remove();
    toast = document.createElement('a');
    toast.href = INBOX + (data.threads[0] ? '&thread=' + data.threads[0].id : '');
    toast.className = 'ntz-alert-toast';
    toast.setAttribute('dir', RTL ? 'rtl' : 'ltr');
    toast.setAttribute('role', 'status');
    var strong = document.createElement('strong');
    strong.textContent = TITLE + ' (' + data.count + ')';
    var small = document.createElement('small');
    small.textContent = data.threads.map(function (t) { return t.name; }).slice(0, 3).join('، ') + ' — ' + OPEN;
    toast.appendChild(strong);
    toast.appendChild(small);
    var x = document.createElement('button');
    x.type = 'button';
    x.textContent = '×';
    x.setAttribute('aria-label', 'close');
    x.addEventListener('click', function (e) { e.preventDefault(); toast.remove(); toast = null; });
    toast.appendChild(x);
    document.body.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('is-shown'); });
  }

  function tick() {
    fetch(ENDPOINT + '&since=' + since, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || !d.ok) return; // no access to the module: stay silent
        if (d.count > 0 && (d.fresh || first) && d.threads.length) show(d);
        if (d.count === 0 && toast) { toast.remove(); toast = null; }
        since = d.last_message_id;
        first = false;
        try { sessionStorage.setItem(KEY, String(since)); } catch (e) {}
        setTimeout(tick, document.hidden ? 60000 : 15000);
      })
      .catch(function () { setTimeout(tick, 60000); });
  }

  var css = document.createElement('style');
  css.textContent = '.ntz-alert-toast{position:fixed;bottom:18px;inset-inline-end:18px;z-index:99999;display:flex;flex-direction:column;gap:2px;min-width:240px;max-width:340px;padding:12px 38px 12px 14px;border-radius:14px;background:#14161f;color:#fff!important;text-decoration:none!important;box-shadow:0 18px 40px -12px rgba(0,0,0,.45);border-inline-start:4px solid #ffc700;font:13px/1.5 Vazirmatn,Tahoma,system-ui,sans-serif;opacity:0;transform:translateY(12px);transition:opacity .2s,transform .3s cubic-bezier(.2,.9,.3,1.2)}'
    + '.ntz-alert-toast.is-shown{opacity:1;transform:none}.ntz-alert-toast strong{font-size:14px}.ntz-alert-toast small{opacity:.75}'
    + '.ntz-alert-toast button{position:absolute;top:6px;inset-inline-end:8px;border:0;background:none;color:#fff;font-size:18px;cursor:pointer;opacity:.6}'
    + '[dir=rtl].ntz-alert-toast{padding:12px 14px 12px 38px}';
  document.head.appendChild(css);

  setTimeout(tick, 2500);
})();
