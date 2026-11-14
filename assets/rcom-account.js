(function () {
	'use strict';
	var $ = function (s, r) { return (r || document).querySelector(s); };
	var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
	var gone = function (n) { n.parentNode.removeChild(n); };
	var j = function (u, o) {
		return fetch(u, Object.assign({ credentials: 'same-origin' }, o)).then(function (r) {
			return r.json().then(function (d) { if (!r.ok) { throw d; } return d; });
		});
	};

	var lf = $('.rcom-accform');
	if (lf) {
		var api = lf.dataset.api, T = JSON.parse(lf.dataset.t || '{}'), msg = $('.rcom-msg', lf), st, wid, ts, E = lf.elements;
		var say = function (m, e) { msg.textContent = m; msg.className = 'rcom-msg' + (e ? ' err' : ''); };
		var state = function () {
			return st || (st = j(api + 'state').then(function (d) { E.nonce.value = d.nonce; E.stamp.value = d.stamp; }).catch(function () { st = null; }));
		};
		var init = function () {
			state();
			if (!lf.dataset.ts || ts) { return; }
			ts = 1;
			var s = document.createElement('script');
			s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'; s.async = true;
			s.onload = function () { wid = window.turnstile.render($('.rcom-ts', lf), { sitekey: lf.dataset.ts }); };
			document.head.appendChild(s);
		};
		lf.addEventListener('focusin', init, { once: true });
		lf.addEventListener('submit', function (ev) {
			ev.preventDefault();
			var b = $('button[type=submit]', lf); b.disabled = true;
			state().then(function () { return j(api + 'login-request', { method: 'POST', body: new FormData(lf) }); })
				.then(function (r) { say(r.message); lf.reset(); })
				.catch(function (x) { say((x && x.message) || T.err, 1); st = null; })
				.then(function () { b.disabled = false; if (wid !== undefined) { window.turnstile.reset(wid); } });
		});
	}

	var a = $('.rcom-acc');
	if (!a) { return; }
	var API = a.dataset.api, N = a.dataset.nonce, TT = JSON.parse(a.dataset.t || '{}'), box = $('.rcom-msg', a);
	var tell = function (m, e) { box.textContent = m; box.className = 'rcom-msg' + (e ? ' err' : ''); };
	var post = function (path, o) {
		var f = new FormData(); Object.keys(o || {}).forEach(function (k) { f.append(k, o[k]); });
		return j(API + path, { method: 'POST', body: f, headers: { 'X-RCOM-Nonce': N } });
	};
	var fail = function (x) {
		if (x && x.code === 'rcom_a_stale') {
			box.className = 'rcom-msg err'; box.textContent = TT.stale + ' ';
			var b = document.createElement('button'); b.type = 'button'; b.className = 'rcom-btn'; b.textContent = TT.newlink;
			b.onclick = function () { post('reauth').then(function (r) { tell(r.message || TT.sent); }).catch(function () { tell(TT.err, 1); }); };
			box.appendChild(b);
		} else { tell((x && x.message) || TT.err, 1); }
	};

	// tabs: without JS every section stays visible
	var tabs = $$('.rcom-tab', a), secs = $$('.rcom-sec', a);
	var show = function (id) {
		tabs.forEach(function (t) { t.setAttribute('aria-selected', t.id === 'rcom-tab-' + id ? 'true' : 'false'); });
		secs.forEach(function (s) { s.hidden = s.id !== 'rcom-sec-' + id; });
	};
	tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.id.replace('rcom-tab-', '')); }); });
	if (tabs.length) { show(tabs[0].id.replace('rcom-tab-', '')); }

	a.addEventListener('click', function (ev) {
		var b = ev.target.closest('[data-act]');
		if (!b) { return; }
		var act = b.dataset.act;
		if (act === 'del-review') {
			var it = b.closest('.rcom-item');
			if (!confirm(TT.sure)) { return; }
			post('review-delete', { id: it.dataset.id }).then(function () { gone(it); tell(TT.gone); }).catch(fail);
		} else if (act === 'vote') {
			var row = b.closest('.rcom-vrow');
			post('vote', { post: row.dataset.post, vote: b.dataset.v }).then(function () {
				if (b.dataset.v === 'remove') { gone(row); } else { $$('.rcom-hbtn', row).forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); }
				tell(TT.gone);
			}).catch(fail);
		} else if (act === 'export') {
			post('export').then(function (d) {
				var u = URL.createObjectURL(new Blob([JSON.stringify(d, null, 2)], { type: 'application/json' })), l = document.createElement('a');
				l.href = u; l.download = 'my-data.json'; document.body.appendChild(l); l.click(); gone(l); URL.revokeObjectURL(u);
			}).catch(fail);
		} else if (act === 'unsub') {
			post('unsubscribe').then(function () { tell(TT.gone); }).catch(fail);
		} else if (act === 'logout' || act === 'logout-all') {
			post(act).then(function () { location.reload(); }).catch(fail);
		} else if (act === 'delete-all') {
			var c = $('[data-confirm]', a);
			if (!c.checked) { tell(TT.confirm, 1); return; }
			if (!confirm(TT.sureall)) { return; }
			post('delete-all', { confirm: '1' }).then(function () { location.reload(); }).catch(fail);
		}
	});
})();
