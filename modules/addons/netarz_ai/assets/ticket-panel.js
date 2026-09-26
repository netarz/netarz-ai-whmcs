/*! NetArz AI for WHMCS — AI draft panel on the admin ticket page. */
(function () {
  'use strict';

  var panel = document.querySelector('[data-ticket-panel]');
  if (!panel || panel.getAttribute('data-ready')) return;
  panel.setAttribute('data-ready', '1');

  var S = JSON.parse((document.querySelector('[data-tp-strings]') || { textContent: '{}' }).textContent);
  var ENDPOINT = panel.getAttribute('data-endpoint');
  var CSRF = panel.getAttribute('data-csrf');
  var TICKET = panel.getAttribute('data-ticket');

  var box = panel.querySelector('[data-tp-draft]');
  var text = panel.querySelector('[data-tp-text]');
  var conf = panel.querySelector('[data-tp-conf]');
  var reason = panel.querySelector('[data-tp-reason]');
  var status = panel.querySelector('[data-tp-status]');
  var outcome = panel.querySelector('[data-tp-outcome]');
  var draftText = text ? text.innerText : '';

  function post(action, data) {
    var body = new URLSearchParams();
    body.append('_ntz', CSRF);
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
    return fetch(ENDPOINT + action, {
      method: 'POST', credentials: 'same-origin', body: body,
      headers: { 'Accept': 'application/json', 'X-Ntz-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); }).catch(function () { return { ok: false }; });
  }

  function setDraft(job) {
    if (!job || !job.draft) {
      box.hidden = true;
      status.textContent = S.nothing;
      return;
    }
    draftText = job.draft;
    text.textContent = job.draft;
    conf.textContent = S.confidence + ': ' + job.confidence + '%';
    reason.textContent = job.reason_label || job.reason || '';
    panel.querySelectorAll('[data-tp="use"], [data-tp="dismiss"]').forEach(function (b) { b.setAttribute('data-id', job.id); });
    box.hidden = false;
    status.textContent = '';
    if (outcome) {
      outcome.className = 'ntz-pill is-' + (job.outcome || job.status);
    }
  }

  /** The WHMCS reply box, whatever the admin theme calls it. */
  function replyBox() {
    return document.querySelector('#replymessage') || document.querySelector('textarea[name="message"]') || document.querySelector('#frmTicketReply textarea');
  }

  panel.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-tp]');
    if (!btn) return;
    var what = btn.getAttribute('data-tp');

    if (what === 'generate') {
      btn.disabled = true;
      status.textContent = S.working;
      panel.classList.add('is-working');
      post('ticket_draft', { ticket_id: TICKET }).then(function (r) {
        btn.disabled = false;
        panel.classList.remove('is-working');
        if (r.ok) {
          setDraft(r.job);
          if (r.job && !r.job.draft) status.textContent = (r.job.error || r.outcome || S.nothing);
        } else {
          status.textContent = r.error || S.error;
        }
      });
    }

    if (what === 'use') {
      var target = replyBox();
      if (target) {
        target.value = (target.value ? target.value.replace(/\s+$/, '') + '\n\n' : '') + draftText;
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.focus();
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        status.textContent = S.inserted;
      } else if (navigator.clipboard) {
        navigator.clipboard.writeText(draftText);
        status.textContent = S.inserted;
      }
      post('ticket_draft_mark', { id: btn.getAttribute('data-id'), status: 'used' });
    }

    if (what === 'dismiss') {
      post('ticket_draft_mark', { id: btn.getAttribute('data-id'), status: 'dismissed' });
      box.hidden = true;
    }

    if (what === 'pause') {
      var paused = btn.getAttribute('data-paused') !== '1';
      post('ticket_pause', { ticket_id: TICKET, paused: paused ? 1 : 0 }).then(function (r) {
        if (!r.ok) return;
        btn.setAttribute('data-paused', paused ? '1' : '0');
        btn.querySelector('span').textContent = paused ? S.resume : S.pause;
        status.textContent = paused ? S.paused_note : '';
      });
    }
  });
})();
