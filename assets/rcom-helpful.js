(function () {
	'use strict';
	var j = function (u, o) {
		return fetch(u, Object.assign({ credentials: 'same-origin' }, o)).then(function (r) {
			return r.json().then(function (d) { if (!r.ok) { throw d; } return d; });
		});
	};
	var I = {}; try { I = JSON.parse(document.getElementById('rcom-i18n').textContent); } catch (e) {}
	var api = I.api || '', T = I.hf || {};
	[].slice.call(document.querySelectorAll('.rcom-helpful')).forEach(function (w) {
		var pid = w.dataset.post, st, S = {}, yes = +w.dataset.yes || 0, cur = '', wid, tsOn;
		var btns = [].slice.call(w.querySelectorAll('.rcom-hbtn[data-v]')), f = w.querySelector('.rcom-hform'), msg = w.querySelector('.rcom-hmsg'), cnt = w.querySelector('.rcom-hcount'), lab = f.querySelector('label'), ta = f.querySelector('textarea');
		var post = function (path, o) { var d = new FormData(); Object.keys(o).forEach(function (k) { d.append(k, o[k]); }); d.append('post', pid); d.append('nonce', S.nonce); d.append('stamp', S.stamp); return j(api + path, { method: 'POST', body: d }); };
		var say = function (t) { msg.textContent = t; };
		var count = function () {
			var t = yes >= T.thr ? (yes === 1 ? T.count.one : T.count.other).replace('%s', yes.toLocaleString()) : '';
			cnt.textContent = t;
		};
		var press = function (v) { cur = v; btns.forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.v === v ? 'true' : 'false'); }); };
		var rm = function () {
			if (!S.known || w.querySelector('.rcom-hrm')) { return; }
			var b = document.createElement('button'); b.type = 'button'; b.className = 'rcom-hrm'; b.textContent = T.remove;
			b.onclick = function () { post('helpful/remove', {}).then(function (r) { yes = r.yes; count(); press(''); f.hidden = true; say(''); b.parentNode.removeChild(b); }).catch(function () { say(T.err); }); };
			w.appendChild(b);
		};
		function load() {
			return st || (st = j(api + 'helpful/state?post=' + pid).then(function (d) { S = d; yes = d.yes; if (d.vote) { press(d.vote); } count(); rm(); }).catch(function () { st = null; }));
		}
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (e) { if (e[0].isIntersecting) { io.disconnect(); load(); } }, { rootMargin: '300px' });
			io.observe(w);
		} else { load(); }
		btns.forEach(function (b) {
			b.addEventListener('click', function () {
				var v = b.dataset.v, prev = cur;
				load().then(function () {
					if (!S.nonce) { say(T.err); return; }
					return post('helpful/vote', { vote: v }).then(function (r) {
						if (prev !== v) { yes = r.yes !== undefined ? r.yes : yes; }
						press(v); count(); S.known = S.known; rm();
						say(v === 'yes' ? T.yes_t : T.no_t);
						lab.textContent = v === 'yes' ? T.yes_q : T.no_q;
						f.hidden = false;
						if (w.dataset.ts && !tsOn) {
							tsOn = 1;
							var s = document.createElement('script'); s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'; s.async = true;
							s.onload = function () { wid = window.turnstile.render(f.querySelector('.rcom-hts'), { sitekey: w.dataset.ts }); };
							document.head.appendChild(s);
						}
					});
				}).catch(function (x) { say((x && x.message) || T.err); });
			});
		});
		f.addEventListener('submit', function (ev) {
			ev.preventDefault();
			var o = { body: ta.value, website: f.elements.website.value };
			var t = f.querySelector('input[name="cf-turnstile-response"]'); if (t) { o['cf-turnstile-response'] = t.value; }
			post('helpful/feedback', o).then(function () { say(T.sent); f.hidden = true; ta.value = ''; })
				.catch(function (x) { say((x && x.message) || T.err); if (wid !== undefined) { window.turnstile.reset(wid); } });
		});
	});
})();
