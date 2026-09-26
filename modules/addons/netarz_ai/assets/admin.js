/*! NetArz AI for WHMCS — admin page. */
(function () {
  'use strict';

  var root = document.querySelector('.ntz[data-endpoint]');
  if (!root) return;

  var ENDPOINT = root.getAttribute('data-endpoint');
  var CSRF = root.getAttribute('data-csrf');
  var RTL = root.getAttribute('dir') === 'rtl';

  function $(sel, ctx) { return (ctx || root).querySelector(sel); }
  function $all(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }

  function call(action, data, method) {
    var opts = { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
    var url = ENDPOINT + action;
    if (method === 'POST' || data) {
      var body = new URLSearchParams();
      body.append('_ntz', CSRF);
      Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
      opts.method = 'POST';
      opts.headers['X-Ntz-Token'] = CSRF;
      opts.body = body;
    }
    return fetch(url, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'HTTP ' + r.status }; });
    }).catch(function (e) { return { ok: false, error: String(e) }; });
  }

  function toast(text, type) {
    var t = el('div', 'ntz-toast is-' + (type || 'info'), text);
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('is-shown'); });
    setTimeout(function () {
      t.classList.remove('is-shown');
      setTimeout(function () { t.remove(); }, 300);
    }, 3200);
  }

  function linkify(node, text) {
    var re = /(https?:\/\/[^\s<>"'«»]+[^\s<>"'«».,،؛:!?)\]])/g;
    var last = 0, m;
    while ((m = re.exec(text)) !== null) {
      if (m.index > last) node.appendChild(document.createTextNode(text.slice(last, m.index)));
      var a = el('a', null, m[0]);
      a.href = m[0]; a.target = '_blank'; a.rel = 'noopener nofollow'; a.dir = 'ltr';
      node.appendChild(a);
      last = m.index + m[0].length;
    }
    if (last < text.length) node.appendChild(document.createTextNode(text.slice(last)));
  }

  /* ------------------------------------------------------------ dashboard */

  $all('[data-action="refresh-balance"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.classList.add('is-spinning');
      call('balance&fresh=1').then(function (r) {
        btn.classList.remove('is-spinning');
        if (r.ok && r.balance && r.balance.ok) {
          var usd = $('[data-balance-usd]');
          if (usd) usd.textContent = r.balance.usd_display || ('$' + Number(r.balance.usd).toFixed(2));
          toast(r.balance.usd_display || ('$' + Number(r.balance.usd).toFixed(2)), 'success');
        } else {
          toast((r.balance && r.balance.error) || r.error || 'Error', 'error');
        }
      });
    });
  });

  /* -------------------------------------------------------------- settings */

  var testBtn = $('[data-action="test-connection"]');
  if (testBtn) {
    testBtn.addEventListener('click', function () {
      var out = $('[data-test-result]');
      var keyInput = $('#ntz-api_key');
      if (keyInput && keyInput.value && keyInput.value.indexOf('••') === -1) {
        out.hidden = false;
        out.className = 'ntz-test-result is-warn';
        out.textContent = RTL ? 'اول تنظیمات را ذخیره کنید، بعد اتصال را آزمایش کنید.' : 'Save the settings first, then test the connection.';
        return;
      }
      testBtn.disabled = true;
      call('test_connection', {}).then(function (r) {
        testBtn.disabled = false;
        out.hidden = false;
        if (r.ok && r.balance) {
          out.className = 'ntz-test-result is-ok';
          out.textContent = r.balance.project + ' — ' + (r.balance.usd_display || ('$' + r.balance.usd)) + ' — ' + r.balance.key;
        } else {
          out.className = 'ntz-test-result is-error';
          out.textContent = r.error || (r.balance && r.balance.error) || 'Error';
        }
      });
    });
  }

  var modelInput = $('#ntz-model');
  if (modelInput) {
    var hint = $('[data-model-price]');
    var models = [];
    var showPrice = function () {
      var m = models.filter(function (x) { return x.id === modelInput.value.trim(); })[0];
      if (!hint) return;
      if (m) {
        hint.textContent = m.name + ' — $' + Number(m['in']).toFixed(3) + ' / $' + Number(m.out).toFixed(3) + (RTL ? ' برای هر یک میلیون توکن ورودی / خروجی' : ' per 1M input / output tokens') + (m.json ? '' : (RTL ? ' — این مدل حالت JSON ندارد' : ' — no JSON mode'));
      }
    };
    call('models').then(function (r) {
      if (!r.ok) return;
      models = r.models || [];
      var list = $('#ntz-models');
      models.forEach(function (m) {
        var o = document.createElement('option');
        o.value = m.id;
        o.label = m.name + ' — $' + Number(m['in']).toFixed(2) + ' / $' + Number(m.out).toFixed(2);
        list.appendChild(o);
      });
      showPrice();
    });
    modelInput.addEventListener('input', showPrice);
    modelInput.addEventListener('change', showPrice);
  }

  // Settings navigation: highlight the section in view.
  var nav = $('.ntz-settings-nav');
  if (nav && 'IntersectionObserver' in window) {
    var links = $all('a', nav);
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        links.forEach(function (a) { a.classList.toggle('is-active', a.getAttribute('href') === '#' + e.target.id); });
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    $all('fieldset[id]').forEach(function (f) { io.observe(f); });
  }

  /* --------------------------------------------------------------- tickets */

  $all('[data-action="retry-job"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true;
      call('job_retry', { id: btn.getAttribute('data-id') }).then(function (r) {
        toast(r.ok ? (r.outcome || 'OK') : (r.error || 'Error'), r.ok ? 'success' : 'error');
        if (r.ok) setTimeout(function () { location.reload(); }, 800);
        btn.disabled = false;
      });
    });
  });

  /* ------------------------------------------------------------- knowledge */

  var kForm = $('[data-knowledge-form]');
  if (kForm) {
    var ta = kForm.querySelector('textarea');
    var count = $('[data-kc-count]');
    var paint = function () { if (count) count.textContent = ta.value.length.toLocaleString() + ' / 60,000'; };
    paint();
    ta.addEventListener('input', paint);
    kForm.addEventListener('submit', function (e) {
      e.preventDefault();
      call('save_knowledge', { knowledge_custom: ta.value }).then(function (r) {
        toast(r.ok ? r.message : (r.error || 'Error'), r.ok ? 'success' : 'error');
      });
    });
  }

  var consoleForm = $('[data-console-form]');
  if (consoleForm) {
    var out = $('[data-console-out]');
    var kOut = $('[data-knowledge-out]');
    consoleForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = consoleForm.querySelector('button[type="submit"]');
      btn.disabled = true;
      out.hidden = false;
      out.innerHTML = '';
      out.appendChild(el('div', 'ntz-typing-dots', '')).innerHTML = '<span></span><span></span><span></span>';
      call('test', {
        question: consoleForm.elements.question.value,
        channel: consoleForm.elements.channel.value,
        client_id: consoleForm.elements.client_id.value || 0
      }).then(function (r) {
        btn.disabled = false;
        out.innerHTML = '';
        var head = el('div', 'ntz-console-head');
        head.appendChild(el('span', 'ntz-pill is-' + (r.action || 'failed'), r.action || 'error'));
        if (r.confidence !== undefined) head.appendChild(el('span', 'ntz-muted', (RTL ? 'اطمینان ' : 'confidence ') + r.confidence + '%'));
        if (r.model) head.appendChild(el('span', 'ntz-muted', r.model));
        if (r.tokens) head.appendChild(el('span', 'ntz-muted', r.tokens + ' tokens · $' + Number(r.cost || 0).toFixed(6)));
        out.appendChild(head);
        var body = el('div', 'ntz-console-reply');
        body.dir = 'auto';
        linkify(body, r.reply || r.error || '');
        out.appendChild(body);
        if (r.handoff_reason) out.appendChild(el('p', 'ntz-muted ntz-small', (RTL ? 'دلیل ارجاع: ' : 'Handoff reason: ') + r.handoff_reason));
        if (r.sources && r.sources.length) {
          var ul = el('ul', 'ntz-console-sources');
          r.sources.forEach(function (s) {
            var li = el('li');
            var a = el('a', null, s.title);
            a.href = s.url; a.target = '_blank'; a.rel = 'noopener';
            li.appendChild(a);
            ul.appendChild(li);
          });
          out.appendChild(ul);
        }
      });
    });

    var prev = $('[data-action="preview-knowledge"]');
    prev.addEventListener('click', function () {
      var q = consoleForm.elements.question.value;
      if (!q.trim()) { consoleForm.elements.question.focus(); return; }
      call('knowledge_preview', { question: q }).then(function (r) {
        kOut.hidden = false;
        kOut.textContent = r.ok ? ('[' + r.chars + ' chars]\n\n' + r.text) : (r.error || 'Error');
      });
    });
  }

  /* ----------------------------------------------------------------- inbox */

  var inbox = $('[data-inbox]');
  if (inbox) {
    var S = JSON.parse(($('[data-inbox-strings]') || { textContent: '{}' }).textContent);
    var list = $('[data-thread-list]');
    var convo = $('[data-convo]');
    var inner = $('[data-convo-inner]');
    var empty = $('[data-convo-empty]');
    var msgs = $('[data-messages]');
    var composer = $('[data-composer]');
    var input = $('[data-composer-input]');
    var filter = 'open';
    var current = null;
    var lastId = 0;
    var threadTimer = null;
    var listTimer = null;
    var typingSent = 0;

    var modeLabel = function (t) { return t.status === 'closed' ? S.closed : (S[t.mode] || t.mode); };

    var renderList = function (threads) {
      list.innerHTML = '';
      if (!threads.length) {
        list.appendChild(el('li', 'ntz-empty', S.empty));
        return;
      }
      threads.forEach(function (t) {
        var li = el('li', 'ntz-thread' + (current === t.id ? ' is-active' : '') + (t.unread ? ' is-unread' : '') + ' is-' + t.mode);
        li.tabIndex = 0;
        li.setAttribute('role', 'button');
        var top = el('div', 'ntz-thread-top');
        top.appendChild(el('strong', null, t.name));
        top.appendChild(el('time', 'ntz-muted', (t.time || '').slice(11, 16)));
        li.appendChild(top);
        var last = el('p', 'ntz-thread-last', t.last);
        last.dir = 'auto';
        li.appendChild(last);
        var tags = el('div', 'ntz-thread-tags');
        tags.appendChild(el('span', 'ntz-pill is-' + (t.status === 'closed' ? 'closed' : t.mode), modeLabel(t)));
        tags.appendChild(el('span', 'ntz-muted', t.client_id ? S.client + ' #' + t.client_id : S.guest));
        if (t.unread) tags.appendChild(el('em', 'ntz-badge', String(t.unread)));
        li.appendChild(tags);
        li.addEventListener('click', function () { openThread(t.id); });
        li.addEventListener('keydown', function (e) { if (e.key === 'Enter') openThread(t.id); });
        list.appendChild(li);
      });
    };

    var loadList = function () {
      clearTimeout(listTimer);
      return call('threads&filter=' + filter).then(function (r) {
        if (r.ok) {
          renderList(r.threads || []);
          var badge = $('.ntz-tab [data-waiting]');
          if (badge) badge.textContent = r.waiting;
        }
        listTimer = setTimeout(loadList, document.hidden ? 20000 : 5000);
      });
    };

    var addMsg = function (m) {
      if (msgs.querySelector('[data-id="' + m.id + '"]')) return;
      lastId = Math.max(lastId, m.id);
      var li = el('li', 'ntz-msg is-' + m.sender);
      li.setAttribute('data-id', m.id);
      if (m.sender === 'system') {
        var sys = el('p', 'ntz-system', m.body);
        sys.dir = 'auto';
        li.appendChild(sys);
      } else {
        var who = m.sender === 'visitor' ? S.visitor : m.name;
        var head = el('span', 'ntz-msg-who', who);
        li.appendChild(head);
        var b = el('div', 'ntz-bubble');
        b.dir = 'auto';
        linkify(b, m.body);
        li.appendChild(b);
        var meta = el('span', 'ntz-msg-meta', m.time);
        if (m.meta && m.meta.confidence !== undefined) meta.appendChild(el('span', null, ' · ' + S.confidence + ' ' + m.meta.confidence + '%'));
        if (m.meta && m.meta.handoff) meta.appendChild(el('span', 'is-handoff', ' · ' + S.handoff + ': ' + (m.meta.reason || '')));
        li.appendChild(meta);
      }
      msgs.appendChild(li);
    };

    var paintThread = function (t) {
      $('[data-convo-name]').textContent = t.name + (t.email ? ' — ' + t.email : '');
      var meta = (t.client_id ? S.client + ' #' + t.client_id : S.guest) + (t.ip ? ' · ' + t.ip : '') + (t.visitor_active ? ' · ' + S.online : '');
      $('[data-convo-meta]').textContent = meta;
      var mode = $('[data-convo-mode]');
      mode.className = 'ntz-pill is-' + (t.status === 'closed' ? 'closed' : t.mode);
      mode.textContent = modeLabel(t);
      var hand = $('[data-convo-handoff]');
      if (t.mode === 'waiting' && t.handoff_reason) {
        hand.hidden = false;
        hand.querySelector('span').textContent = S.handoff + ': ' + t.handoff_reason;
      } else {
        hand.hidden = true;
      }
      $('[data-action="takeover"]').hidden = t.mode === 'human';
      $('[data-action="handback"]').hidden = t.mode === 'ai';
      var tk = $('[data-action="to-ticket"]');
      tk.disabled = !!t.ticket_id;
      if (t.ticket_id) tk.title = '#' + t.ticket_id;
    };

    var pollThread = function (scroll) {
      clearTimeout(threadTimer);
      if (!current) return Promise.resolve();
      var id = current;
      return call('thread&id=' + id + '&after=' + lastId).then(function (r) {
        if (current !== id) return;
        if (r.ok) {
          var nearBottom = msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight < 140;
          (r.messages || []).forEach(addMsg);
          paintThread(r.thread);
          if (scroll || nearBottom) msgs.scrollTop = msgs.scrollHeight;
        }
        threadTimer = setTimeout(pollThread, document.hidden ? 15000 : 2500);
      });
    };

    var openThread = function (id) {
      current = id;
      lastId = 0;
      msgs.innerHTML = '';
      empty.hidden = true;
      inner.hidden = false;
      convo.classList.add('is-open');
      $all('.ntz-thread', list).forEach(function (li) { li.classList.remove('is-active'); });
      pollThread(true).then(function () { input.focus(); });
      if (history.replaceState) history.replaceState(null, '', location.pathname + location.search.replace(/&thread=\d+/, '') + '&thread=' + id);
    };

    $all('[data-filter]').forEach(function (b) {
      b.addEventListener('click', function () {
        $all('[data-filter]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
        filter = b.getAttribute('data-filter');
        loadList();
      });
    });

    composer.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = input.value.trim();
      if (!text || !current) return;
      input.value = '';
      call('chat_send', { id: current, body: text }).then(function (r) {
        if (r.ok && r.message) {
          addMsg(r.message);
          msgs.scrollTop = msgs.scrollHeight;
          pollThread(true);
        } else {
          input.value = text;
          toast(r.error || S.error, 'error');
        }
      });
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
        e.preventDefault();
        composer.requestSubmit ? composer.requestSubmit() : composer.dispatchEvent(new Event('submit'));
      }
    });
    input.addEventListener('input', function () {
      if (!current || Date.now() - typingSent < 4000) return;
      typingSent = Date.now();
      call('chat_typing', { id: current });
    });

    var act = function (name, fn) {
      var b = $('[data-action="' + name + '"]');
      if (b) b.addEventListener('click', function () { if (current) fn(b); });
    };
    act('takeover', function () { call('chat_mode', { id: current, mode: 'human' }).then(function () { pollThread(); loadList(); }); });
    act('handback', function () { call('chat_mode', { id: current, mode: 'ai' }).then(function () { pollThread(true); loadList(); }); });
    act('close', function () { call('chat_close', { id: current }).then(function () { pollThread(); loadList(); }); });
    act('to-ticket', function (b) {
      b.disabled = true;
      call('chat_ticket', { id: current }).then(function (r) {
        if (r.ok) {
          toast(S.ticket_done + (r.tid ? ' #' + r.tid : ''), 'success');
          if (r.admin_url) window.open(r.admin_url, '_blank');
        } else {
          b.disabled = false;
          toast(r.error || S.error, 'error');
        }
        pollThread(true);
      });
    });
    act('delete', function () {
      if (!window.confirm(S.confirm_delete)) return;
      call('chat_delete', { id: current }).then(function () {
        current = null;
        inner.hidden = true;
        empty.hidden = false;
        convo.classList.remove('is-open');
        loadList();
      });
    });
    act('back', function () {
      convo.classList.remove('is-open');
    });

    loadList();
    var m = /[?&]thread=(\d+)/.exec(location.search);
    if (m) openThread(+m[1]);
  }
})();
