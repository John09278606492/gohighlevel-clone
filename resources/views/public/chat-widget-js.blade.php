/* {{ config('app.name') }} chat widget for {{ $account->name }} */
(function () {
    if (window.__leChat) return; window.__leChat = true;
    var cfg = {!! json_encode([
        'send' => route('widget.send', $account),
        'poll' => route('widget.poll', $account),
        'color' => $account->chat_widget_color ?: '#4f46e5',
        'title' => $account->name,
        'greeting' => $account->chat_widget_greeting ?: 'Hi! How can we help? Leave a message and we\'ll get right back to you.',
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) !!};
    var KEY = 'le_chat_visitor_{{ $account->id }}';
    var store = { get: function () { try { return localStorage.getItem(KEY); } catch (e) { return null; } }, set: function (v) { try { localStorage.setItem(KEY, v); } catch (e) {} } };
    var visitor = store.get(), timer = null, seen = 0;

    var css = '#le-chat-btn{position:fixed;right:20px;bottom:20px;width:56px;height:56px;border-radius:50%;border:0;cursor:pointer;color:#fff;box-shadow:0 6px 20px rgba(0,0,0,.2);z-index:2147483646;font-size:26px;line-height:56px}'
        + '#le-chat{position:fixed;right:20px;bottom:88px;width:340px;max-width:calc(100vw - 40px);height:480px;max-height:calc(100vh - 120px);background:#fff;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.25);display:none;flex-direction:column;overflow:hidden;z-index:2147483647;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#111}'
        + '#le-chat.open{display:flex}#le-chat header{color:#fff;padding:14px 16px;font-weight:600}'
        + '#le-chat .le-log{flex:1;overflow-y:auto;padding:12px;background:#f7f7f8;display:flex;flex-direction:column;gap:8px}'
        + '#le-chat .le-m{max-width:80%;padding:8px 12px;border-radius:12px;white-space:pre-wrap;word-wrap:break-word}'
        + '#le-chat .le-agent{background:#fff;border:1px solid #e5e7eb;align-self:flex-start}#le-chat .le-visitor{color:#fff;align-self:flex-end}'
        + '#le-chat form{display:flex;flex-direction:column;gap:6px;padding:10px;border-top:1px solid #eee}'
        + '#le-chat input,#le-chat textarea{border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;font:inherit;resize:none}'
        + '#le-chat button[type=submit]{border:0;border-radius:8px;padding:9px;color:#fff;font-weight:600;cursor:pointer}'
        + '#le-chat .le-err{color:#b91c1c;font-size:12px}';
    var style = document.createElement('style'); style.textContent = css; document.head.appendChild(style);

    var btn = document.createElement('button'); btn.id = 'le-chat-btn'; btn.type = 'button'; btn.setAttribute('aria-label', 'Chat with us'); btn.textContent = '💬'; btn.style.background = cfg.color;
    var box = document.createElement('div'); box.id = 'le-chat'; box.setAttribute('role', 'dialog');
    box.innerHTML = '<header></header><div class="le-log" aria-live="polite"></div>'
        + '<form><div class="le-who"><input name="name" placeholder="Your name" maxlength="120"><input name="email" type="email" placeholder="Email" maxlength="255"><input name="phone" placeholder="Phone (optional)" maxlength="40"></div>'
        + '<textarea name="message" rows="2" placeholder="Type your message" required maxlength="2000"></textarea><div class="le-err"></div><button type="submit">Send</button></form>';
    document.body.appendChild(btn); document.body.appendChild(box);
    var head = box.querySelector('header'), log = box.querySelector('.le-log'), form = box.querySelector('form'), who = box.querySelector('.le-who'), err = box.querySelector('.le-err');
    head.textContent = cfg.title; head.style.background = cfg.color; form.querySelector('button').style.background = cfg.color;
    who.style.display = visitor ? 'none' : 'flex'; who.style.flexDirection = 'column'; who.style.gap = '6px';

    function bubble(from, text) { var d = document.createElement('div'); d.className = 'le-m le-' + from; d.textContent = text; if (from === 'visitor') d.style.background = cfg.color; log.appendChild(d); }
    function render(messages) {
        if (messages.length === seen) return; seen = messages.length;
        log.innerHTML = ''; bubble('agent', cfg.greeting);
        messages.forEach(function (m) { bubble(m.from, m.body); }); log.scrollTop = log.scrollHeight;
    }
    function poll() {
        if (!visitor) return;
        fetch(cfg.poll + '?visitor=' + encodeURIComponent(visitor)).then(function (r) { return r.json(); }).then(function (d) { render(d.messages || []); }).catch(function () {});
    }
    bubble('agent', cfg.greeting);

    btn.addEventListener('click', function () {
        var open = box.classList.toggle('open');
        if (open) { poll(); timer = setInterval(poll, 8000); } else { clearInterval(timer); }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault(); err.textContent = '';
        var fd = new FormData(form), body = { message: fd.get('message'), page: location.href.slice(0, 500) };
        if (visitor) { body.visitor = visitor; } else {
            body.name = fd.get('name'); body.email = fd.get('email'); body.phone = fd.get('phone');
            if (!body.email && !body.phone) { err.textContent = 'Please add your email or phone so we can reply.'; return; }
        }
        fetch(cfg.send, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(body) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok) { err.textContent = (res.d && res.d.message) || 'Could not send. Please try again.'; return; }
                visitor = res.d.visitor; store.set(visitor); who.style.display = 'none';
                form.message.value = ''; render(res.d.messages || []);
            })
            .catch(function () { err.textContent = 'Could not send. Please try again.'; });
    });
})();
