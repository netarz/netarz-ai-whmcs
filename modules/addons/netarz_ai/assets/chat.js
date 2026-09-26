/*!
 * NetArz AI chat widget for WHMCS — https://github.com/netarz/netarz-ai-whmcs
 * No dependencies. Everything the visitor or the model writes is inserted as text, never as HTML.
 */
(function () {
  'use strict';

  var script = document.currentScript || document.querySelector('script[data-netarz-chat]');
  if (!script || window.NetArzChat) return;

  var ENDPOINT = script.getAttribute('data-endpoint');
  var TOKEN_KEY = 'netarz_ai_chat_token';
  var OPEN_KEY = 'netarz_ai_chat_open';
  // Lucide icons (ISC licence), inlined.
  var ICONS = {
    "message-circle": "<path fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092a10 10 0 1 0-4.777-4.719\"/>",
    "x": "<path fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M18 6L6 18M6 6l12 12\"/>",
    "send": "<path fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11zm7.318-19.539l-10.94 10.939\"/>",
    "headset": "<g fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\"><path d=\"M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zm0 0a9 9 0 1 1 18 0m0 0v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2z\"/><path d=\"M21 16v2a4 4 0 0 1-4 4h-5\"/></g>",
    "bot": "<g fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\"><path d=\"M12 8V4H8\"/><rect width=\"16\" height=\"12\" x=\"4\" y=\"8\" rx=\"2\"/><path d=\"M2 14h2m16 0h2m-7-1v2m-6-2v2\"/></g>",
    "check": "<path fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M20 6L9 17l-5-5\"/>",
    "check-check": "<path fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M18 6L7 17l-5-5m20-2l-7.5 7.5L13 16\"/>",
    "ticket": "<path fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Zm11-4v2m0 10v2m0-8v2\"/>",
    "arrow-up-right": "<path fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M7 7h10v10M7 17L17 7\"/>",
    "sparkles": "<g fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\"><path d=\"M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594zM20 2v4m2-2h-4\"/><circle cx=\"4\" cy=\"20\" r=\"2\"/></g>",
    "user": "<g fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\"><path d=\"M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2\"/><circle cx=\"12\" cy=\"7\" r=\"4\"/></g>",
    "chevron-down": "<path fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"m6 9l6 6l6-6\"/>"
  };

  var cfg = null;
  var S = {}; // strings
  var token = store('get', TOKEN_KEY) || '';
  var lastId = 0;
  var isOpen = false;
  var unread = 0;
  var pollTimer = null;
  var replyTimer = null;
  var replyInFlight = false;
  var sending = 0;
  var seenByAdmin = 0;
  var state = null;
  var els = {};

  /* ------------------------------------------------------------ helpers */

  function store(op, key, value) {
    try {
      if (op === 'get') return window.localStorage.getItem(key);
      if (op === 'del') return window.localStorage.removeItem(key);
      window.localStorage.setItem(key, value);
    } catch (e) { /* private mode */ }
    return null;
  }

  function icon(name, size) {
    var s = size || 18;
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' + s + '" height="' + s + '" viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[name] || '') + '</svg>';
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }

  function api(action, body, method) {
    var url = ENDPOINT + '&na=' + action;
    var opts = { method: method || (body ? 'POST' : 'GET'), credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-NetArz-Chat': '1' } };
    if (token) opts.headers['X-Chat-Token'] = token;
    if (opts.method === 'POST') {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body || {});
    }
    return fetch(url, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'bad_response' }; });
    }).catch(function () { return { ok: false, error: 'network' }; });
  }

  /** Text with clickable http(s) links; built from text nodes only. */
  function richText(node, text) {
    var re = /(https?:\/\/[^\s<>"'«»]+[^\s<>"'«».,،؛:!?)\]])/g;
    var last = 0;
    var m;
    while ((m = re.exec(text)) !== null) {
      if (m.index > last) node.appendChild(document.createTextNode(text.slice(last, m.index)));
      var a = el('a', null, m[0]);
      a.href = m[0];
      a.target = '_blank';
      a.rel = 'noopener nofollow';
      a.dir = 'ltr';
      node.appendChild(a);
      last = m.index + m[0].length;
    }
    if (last < text.length) node.appendChild(document.createTextNode(text.slice(last)));
  }

  /* --------------------------------------------------------------- build */

  function build() {
    var root = el('div', 'ntzc');
    root.id = 'netarz-chat';
    root.setAttribute('dir', cfg.rtl ? 'rtl' : 'ltr');
    root.setAttribute('lang', cfg.lang);
    root.classList.add(cfg.position === 'left' ? 'is-left' : 'is-right');
    root.style.setProperty('--ntzc-accent', cfg.color);
    root.style.setProperty('--ntzc-on-accent', cfg.text_color);

    root.innerHTML =
      '<button type="button" class="ntzc-launcher" aria-haspopup="dialog" aria-expanded="false">' +
        '<span class="ntzc-launcher-icon is-chat">' + icon('message-circle', 26) + '</span>' +
        '<span class="ntzc-launcher-icon is-close">' + icon('x', 24) + '</span>' +
        '<span class="ntzc-unread" hidden></span>' +
      '</button>' +
      '<section class="ntzc-panel" role="dialog" aria-modal="false" hidden>' +
        '<header class="ntzc-head">' +
          '<span class="ntzc-avatar">' + icon('sparkles', 20) + '<i class="ntzc-dot"></i></span>' +
          '<div class="ntzc-who"><strong class="ntzc-name"></strong><small class="ntzc-status"></small></div>' +
          '<button type="button" class="ntzc-close">' + icon('chevron-down', 22) + '</button>' +
        '</header>' +
        '<div class="ntzc-scroll">' +
          '<div class="ntzc-intro"></div>' +
          '<ol class="ntzc-list" aria-live="polite" aria-relevant="additions"></ol>' +
          '<div class="ntzc-typing" hidden><span></span><span></span><span></span></div>' +
          '<div class="ntzc-offer" hidden></div>' +
        '</div>' +
        '<form class="ntzc-identity" hidden novalidate>' +
          '<p class="ntzc-identity-lead"></p>' +
          '<input class="ntzc-input" name="name" autocomplete="name" required maxlength="120">' +
          '<input class="ntzc-input" name="email" type="email" autocomplete="email" dir="ltr" required maxlength="190">' +
          '<button type="submit" class="ntzc-primary"></button>' +
          '<p class="ntzc-error" role="alert" hidden></p>' +
        '</form>' +
        '<form class="ntzc-composer">' +
          '<textarea rows="1" class="ntzc-textarea" dir="auto"></textarea>' +
          '<button type="submit" class="ntzc-send">' + icon('send', 18) + '</button>' +
        '</form>' +
        '<p class="ntzc-error is-composer" role="alert" hidden></p>' +
        '<footer class="ntzc-foot"></footer>' +
      '</section>';

    document.body.appendChild(root);

    els.root = root;
    els.launcher = root.querySelector('.ntzc-launcher');
    els.unread = root.querySelector('.ntzc-unread');
    els.panel = root.querySelector('.ntzc-panel');
    els.name = root.querySelector('.ntzc-name');
    els.status = root.querySelector('.ntzc-status');
    els.close = root.querySelector('.ntzc-close');
    els.scroll = root.querySelector('.ntzc-scroll');
    els.intro = root.querySelector('.ntzc-intro');
    els.list = root.querySelector('.ntzc-list');
    els.typing = root.querySelector('.ntzc-typing');
    els.offer = root.querySelector('.ntzc-offer');
    els.identity = root.querySelector('.ntzc-identity');
    els.composer = root.querySelector('.ntzc-composer');
    els.textarea = root.querySelector('.ntzc-textarea');
    els.send = root.querySelector('.ntzc-send');
    els.error = root.querySelector('.ntzc-error.is-composer');
    els.foot = root.querySelector('.ntzc-foot');

    els.launcher.setAttribute('aria-label', S.open);
    els.close.setAttribute('aria-label', S.minimize);
    els.close.title = S.minimize;
    els.panel.setAttribute('aria-label', S.title);
    els.send.setAttribute('aria-label', S.send);
    els.send.title = S.send;
    els.textarea.placeholder = S.placeholder;
    els.textarea.maxLength = cfg.max_length;
    els.textarea.setAttribute('aria-label', S.placeholder);
    els.name.textContent = cfg.agent;

    var greet = el('div', 'ntzc-greeting');
    var hello = el('p', 'ntzc-bubble is-ai');
    hello.dir = 'auto';
    richText(hello, cfg.greeting);
    greet.appendChild(hello);
    els.intro.appendChild(greet);

    if (cfg.credit) {
      var credit = el('a', null, S.credit);
      credit.href = 'https://netarz.ir/ai-api?utm_source=whmcs-plugin&utm_medium=chat-widget&utm_campaign=netarz-ai-whmcs';
      credit.target = '_blank';
      credit.rel = 'noopener';
      els.foot.appendChild(credit);
    } else {
      els.foot.hidden = true;
    }

    var idForm = els.identity;
    idForm.querySelector('.ntzc-identity-lead').textContent = S.identity_lead;
    idForm.elements.name.placeholder = S.name;
    idForm.elements.name.setAttribute('aria-label', S.name);
    idForm.elements.email.placeholder = S.email;
    idForm.elements.email.setAttribute('aria-label', S.email);
    idForm.querySelector('button').textContent = S.start;

    wire();
  }

  function wire() {
    els.launcher.addEventListener('click', function () { isOpen ? close() : open(); });
    els.close.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen) close();
    });

    els.composer.addEventListener('submit', function (e) {
      e.preventDefault();
      submit();
    });
    els.textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
        e.preventDefault();
        submit();
      }
    });
    els.textarea.addEventListener('input', function () {
      autosize();
      // Still typing: hold the AI back so the whole burst gets one answer.
      if (replyTimer) scheduleReply();
    });

    els.identity.addEventListener('submit', function (e) {
      e.preventDefault();
      var f = els.identity;
      var name = f.elements.name.value.trim();
      var email = f.elements.email.value.trim();
      var err = f.querySelector('.ntzc-error');
      if (name.length < 2 || !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
        err.textContent = S.identity_invalid;
        err.hidden = false;
        return;
      }
      err.hidden = true;
      startThread(name, email).then(function (ok) {
        if (ok) {
          f.hidden = true;
          els.composer.hidden = false;
          els.textarea.focus();
        }
      });
    });

    window.addEventListener('hashchange', function () {
      if (location.hash === '#netarz-chat') open();
    });
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden && token) poll();
    });
  }

  function autosize() {
    var t = els.textarea;
    t.style.height = 'auto';
    t.style.height = Math.min(t.scrollHeight, 140) + 'px';
  }

  /* ------------------------------------------------------------- state */

  function open() {
    isOpen = true;
    store('set', OPEN_KEY, '1');
    els.root.classList.add('is-open');
    els.panel.hidden = false;
    els.launcher.setAttribute('aria-expanded', 'true');
    unread = 0;
    paintUnread();
    showComposer();
    requestAnimationFrame(function () {
      els.root.classList.add('is-shown');
      scrollDown(true);
      if (!els.composer.hidden && window.matchMedia('(min-width: 640px)').matches) els.textarea.focus();
    });
    if (token) poll();
    restartPolling();
  }

  function close() {
    isOpen = false;
    store('set', OPEN_KEY, '0');
    els.root.classList.remove('is-shown');
    els.launcher.setAttribute('aria-expanded', 'false');
    setTimeout(function () {
      if (!isOpen) {
        els.panel.hidden = true;
        els.root.classList.remove('is-open');
      }
    }, 220);
    restartPolling();
    els.launcher.focus();
  }

  function showComposer() {
    if (cfg.login_required) {
      els.composer.hidden = true;
      els.identity.hidden = true;
      if (!els.intro.querySelector('.ntzc-login')) {
        var box = el('div', 'ntzc-login');
        box.appendChild(el('p', null, S.login_required));
        var a = el('a', 'ntzc-primary', S.login);
        a.href = cfg.login_url;
        box.appendChild(a);
        els.intro.appendChild(box);
      }
      return;
    }
    var needsIdentity = !token && cfg.needs_identity;
    els.identity.hidden = !needsIdentity;
    els.composer.hidden = needsIdentity;
  }

  function setState(s) {
    if (!s) return;
    state = s;
    els.name.textContent = s.agent || cfg.agent;
    els.root.classList.toggle('is-human', !!s.is_human);
    var status = s.is_human ? S.status_human : (s.mode === 'waiting' ? S.status_waiting : (cfg.ai ? S.status_ai : S.status_team));
    els.status.textContent = status;
    els.typing.hidden = !(s.typing || replyInFlight);
    if (typeof s.seen_by_admin === 'number' && s.seen_by_admin !== seenByAdmin) {
      seenByAdmin = s.seen_by_admin;
      paintSeen();
    }
    paintOffer();
  }

  function paintOffer() {
    var box = els.offer;
    box.innerHTML = '';
    if (!state) { box.hidden = true; return; }
    if (state.ticket_id) {
      box.hidden = true;
      return;
    }
    if (!state.offer_ticket) { box.hidden = true; return; }
    box.hidden = false;
    box.appendChild(el('p', null, S.offer_ticket));
    var b = el('button', 'ntzc-primary');
    b.type = 'button';
    b.innerHTML = icon('ticket', 16);
    b.appendChild(document.createTextNode(' ' + S.make_ticket));
    b.addEventListener('click', function () {
      b.disabled = true;
      api('ticket', { after: lastId }).then(function (r) {
        b.disabled = false;
        if (!r.ok) { showError(r.message || S.err_generic); return; }
        if (r.messages) r.messages.forEach(addMessage);
        setState(r.state);
        if (r.url) {
          var a = el('a', 'ntzc-ticket-link', S.view_ticket);
          a.href = r.url;
          a.target = '_blank';
          a.rel = 'noopener';
          els.list.appendChild(wrapLi(a, 'is-system'));
          scrollDown(true);
        }
      });
    });
    box.appendChild(b);
  }

  function paintUnread() {
    els.unread.hidden = unread <= 0;
    els.unread.textContent = unread > 9 ? '9+' : String(unread);
  }

  function paintSeen() {
    var items = els.list.querySelectorAll('li.is-visitor[data-id]');
    for (var i = 0; i < items.length; i++) {
      var id = +items[i].getAttribute('data-id');
      var tick = items[i].querySelector('.ntzc-tick');
      if (tick) {
        var seen = id <= seenByAdmin;
        tick.innerHTML = icon(seen ? 'check-check' : 'check', 14);
        tick.classList.toggle('is-seen', seen);
        tick.title = seen ? S.seen : S.sent;
      }
    }
  }

  /* ----------------------------------------------------------- messages */

  function wrapLi(child, cls) {
    var li = el('li', 'ntzc-msg ' + cls);
    li.appendChild(child);
    return li;
  }

  function addMessage(m) {
    if (!m || !m.id || els.list.querySelector('li[data-id="' + m.id + '"]')) return;

    // A message we already drew optimistically: swap in the stored one.
    if (m.sender === 'visitor') {
      var pending = els.list.querySelector('li.is-pending');
      if (pending && pending.getAttribute('data-body') === m.body) pending.parentNode.removeChild(pending);
    }

    lastId = Math.max(lastId, m.id);
    var li = el('li', 'ntzc-msg is-' + m.sender);
    li.setAttribute('data-id', m.id);

    if (m.sender === 'system') {
      var sys = el('p', 'ntzc-system');
      sys.dir = 'auto';
      richText(sys, m.body);
      li.appendChild(sys);
    } else {
      if (m.sender !== 'visitor' && m.name) {
        var who = el('span', 'ntzc-author', m.name);
        if (m.sender === 'admin') who.classList.add('is-human');
        li.appendChild(who);
      }
      var bubble = el('div', 'ntzc-bubble is-' + (m.sender === 'visitor' ? 'me' : m.sender));
      bubble.dir = 'auto'; // an English line in a Persian widget keeps its punctuation
      richText(bubble, m.body);
      li.appendChild(bubble);
      var meta = el('span', 'ntzc-meta', m.time);
      if (m.sender === 'visitor') {
        var tick = el('span', 'ntzc-tick');
        meta.appendChild(tick);
      }
      li.appendChild(meta);
    }

    els.list.appendChild(li);
    if (m.sender === 'visitor') paintSeen();
    if (m.sender !== 'visitor' && !isOpen && m.sender !== 'system') {
      unread++;
      paintUnread();
      els.launcher.classList.remove('is-nudge');
      void els.launcher.offsetWidth;
      els.launcher.classList.add('is-nudge');
    }
    scrollDown(m.sender === 'visitor');
  }

  function drawPending(text) {
    var li = el('li', 'ntzc-msg is-visitor is-pending');
    li.setAttribute('data-body', text);
    var bubble = el('div', 'ntzc-bubble is-me');
    bubble.dir = 'auto';
    richText(bubble, text);
    li.appendChild(bubble);
    li.appendChild(el('span', 'ntzc-meta', '…'));
    els.list.appendChild(li);
    scrollDown(true);
    return li;
  }

  function scrollDown(force) {
    var s = els.scroll;
    var near = s.scrollHeight - s.scrollTop - s.clientHeight < 120;
    if (force || near) s.scrollTop = s.scrollHeight;
  }

  function showError(text) {
    els.error.textContent = text;
    els.error.hidden = false;
    clearTimeout(showError.t);
    showError.t = setTimeout(function () { els.error.hidden = true; }, 6000);
  }

  /* --------------------------------------------------------------- flow */

  function startThread(name, email) {
    return api('start', { name: name || '', email: email || '', page: location.href }).then(function (r) {
      if (!r.ok) {
        var err = els.identity.querySelector('.ntzc-error');
        if (!els.identity.hidden) {
          err.textContent = r.message || S.err_generic;
          err.hidden = false;
        } else {
          showError(r.message || S.err_generic);
        }
        return false;
      }
      token = r.token;
      store('set', TOKEN_KEY, token);
      setState(r.state);
      (r.messages || []).forEach(addMessage);
      restartPolling(); // there was nothing to poll before the conversation existed
      return true;
    });
  }

  function submit() {
    var text = els.textarea.value.replace(/\s+$/, '');
    if (!text.trim()) return;
    if (text.length > cfg.max_length) { showError(S.err_too_long); return; }

    els.textarea.value = '';
    autosize();
    var pending = drawPending(text);
    sending++;

    var ready = token ? Promise.resolve(true) : startThread('', '');
    ready.then(function (ok) {
      if (!ok) { sending--; pending.classList.add('is-failed'); return; }
      return api('send', { body: text }).then(function (r) {
        sending--;
        if (!r.ok) {
          if (r.error === 'no_thread') {
            token = '';
            store('del', TOKEN_KEY);
          }
          if (r.error !== 'duplicate') {
            pending.classList.add('is-failed');
            els.textarea.value = els.textarea.value || text;
            autosize();
            showError(r.message || S.err_generic);
          } else if (pending.parentNode) {
            pending.parentNode.removeChild(pending);
          }
          return;
        }
        addMessage(r.message);
        setState(r.state);
        if (state && state.mode === 'ai') scheduleReply();
      });
    });
  }

  function scheduleReply(delay) {
    clearTimeout(replyTimer);
    replyTimer = setTimeout(askForReply, typeof delay === 'number' ? delay : Math.max(400, cfg.burst_ms));
  }

  function askForReply() {
    replyTimer = null;
    if (sending > 0 || els.textarea.value.trim() !== '') { scheduleReply(); return; }
    if (replyInFlight) return;
    replyInFlight = true;
    els.typing.hidden = false;
    scrollDown(false);

    api('reply', {}).then(function (r) {
      replyInFlight = false;
      if (!r.ok) {
        els.typing.hidden = true;
        return;
      }
      if (r.messages) r.messages.forEach(addMessage);
      setState(r.state);
      if (r.status === 'wait' || r.status === 'busy' || r.status === 'superseded') {
        els.typing.hidden = false;
        scheduleReply(r.retry_in || 1000);
      }
    });
  }

  function poll() {
    if (!token) return Promise.resolve();
    return fetch(ENDPOINT + '&na=poll&after=' + lastId, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-NetArz-Chat': '1', 'X-Chat-Token': token }
    }).then(function (r) { return r.json(); }).then(function (r) {
      if (!r || !r.ok) {
        if (r && r.error === 'no_thread') { token = ''; store('del', TOKEN_KEY); }
        return;
      }
      (r.messages || []).forEach(addMessage);
      setState(r.state);
    }).catch(function () { /* offline for a moment */ });
  }

  function restartPolling() {
    clearTimeout(pollTimer);
    var loop = function () {
      var every = !token ? 0 : (document.hidden ? 30000 : (isOpen ? 3000 : 15000));
      if (!every) return;
      pollTimer = setTimeout(function () {
        poll().then(loop);
      }, every);
    };
    loop();
  }

  /* --------------------------------------------------------------- boot */

  function boot() {
    fetch(ENDPOINT + '&na=boot', {
      credentials: 'same-origin',
      headers: token ? { 'Accept': 'application/json', 'X-Chat-Token': token } : { 'Accept': 'application/json' }
    }).then(function (r) { return r.json(); }).then(function (r) {
      if (!r || !r.ok || !r.config) return;
      cfg = r.config;
      S = cfg.strings || {};
      build();
      if (r.thread) {
        (r.thread.messages || []).forEach(addMessage);
        setState(r.thread.state);
        unread = 0;
        paintUnread();
        var msgs = r.thread.messages || [];
        var lastMsg = msgs.length ? msgs[msgs.length - 1] : null;
        if (lastMsg && lastMsg.sender === 'visitor' && r.thread.state.mode === 'ai') scheduleReply(300);
      } else if (token) {
        token = '';
        store('del', TOKEN_KEY);
      }
      showComposer();
      if (location.hash === '#netarz-chat' || store('get', OPEN_KEY) === '1') open();
      restartPolling();
    }).catch(function () { /* the widget simply does not appear */ });
  }

  window.NetArzChat = {
    open: function () { if (cfg) open(); },
    close: function () { if (cfg) close(); }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
