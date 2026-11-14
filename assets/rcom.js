(function () {
	'use strict';
	var $ = function (s, r) { return (r || document).querySelector(s); };
	var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
	var j = function (u, o) {
		return fetch(u, Object.assign({ credentials: 'same-origin' }, o)).then(function (r) {
			return r.json().then(function (d) { if (!r.ok) { throw d; } return d; });
		});
	};
	var post = function (api, path, o) { var f = new FormData(); Object.keys(o).forEach(function (k) { f.append(k, o[k]); }); return j(api + path, { method: 'POST', body: f }); };
	var gone = function (n) { n.parentNode.removeChild(n); };

	$$('.rcom-list').forEach(function (l) {
		var b = $('.rcom-more', l);
		if (!b) { return; }
		b.addEventListener('click', function () {
			b.disabled = true;
			(function next() {
				var n = $$('.rcom-item', l).length;
				j(l.dataset.api + 'reviews?post=' + l.dataset.post + '&offset=' + n + '&per_page=50').then(function (d) {
					var t = document.createElement('div'); t.innerHTML = d.html;
					while (t.firstChild) { l.insertBefore(t.firstChild, b); }
					if (d.html && $$('.rcom-item', l).length < +l.dataset.total) { next(); } else { gone(b); document.dispatchEvent(new Event('rcom:loaded')); }
				}).catch(function () { b.disabled = false; });
			})();
		});
	});

	$$('.rcom-form').forEach(function (f) {
		var api = f.dataset.api, pid = f.dataset.post, msg = $('.rcom-msg', f), T = {}, st, wid, tsReady, E = f.elements;
		try { T = JSON.parse(f.dataset.t || '{}'); } catch (e) {}
		var say = function (m, err) { msg.textContent = m; msg.className = 'rcom-msg' + (err ? ' err' : ''); };
		var q = /[?&]rcom=(confirmed|manage)/.exec(location.search);
		if (q && T[q[1]]) { say(T[q[1]]); }

		function loadState() {
			return st || (st = j(api + 'state?post=' + pid).then(function (d) {
				E.nonce.value = d.nonce; E.stamp.value = d.stamp;
				if (d.me) {
					var e = $('.rcom-email', f); e.hidden = true; E.email.required = false; E.email.value = '';
					if (!E.name.value) { E.name.value = d.me.name; }
					var p = document.createElement('p'); p.className = 'rcom-small';
					p.textContent = T.as.replace('%s', d.me.hint) + ' ';
					var b = document.createElement('button'); b.type = 'button'; b.className = 'rcom-del'; b.textContent = T.notyou;
					b.onclick = function () { post(api, 'forget', {}).then(function () { gone(p); e.hidden = false; E.email.required = true; }); };
					p.appendChild(b); e.parentNode.insertBefore(p, e);
				}
				d.own.forEach(function (id) {
					$$('.rcom-item[data-id="' + id + '"]').forEach(function (it) {
						if ($('.rcom-del', it)) { return; }
						var b = document.createElement('button'); b.type = 'button'; b.className = 'rcom-del'; b.textContent = T.del;
						b.onclick = function () {
							if (!confirm(T.sure)) { return; }
							post(api, 'delete', { id: id, nonce: E.nonce.value }).then(function (r) { gone(it); say(r.message); }).catch(function (x) { say(x.message || T.err, 1); });
						};
						it.appendChild(b);
					});
				});
			}).catch(function () { st = null; }));
		}

		function turnstile() {
			if (!f.dataset.ts || tsReady) { return; }
			tsReady = 1;
			var s = document.createElement('script');
			s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'; s.async = true;
			s.onload = function () { wid = window.turnstile.render($('.rcom-ts', f), { sitekey: f.dataset.ts }); };
			document.head.appendChild(s);
		}
		function init() { loadState(); turnstile(); }
		f.addEventListener('focusin', init, { once: true });
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (e) { if (e[0].isIntersecting) { io.disconnect(); init(); } }, { rootMargin: '200px' });
			io.observe(f);
		} else { init(); }

		var sub = $('button[type=submit]', f);
		f.addEventListener('submit', function (ev) {
			ev.preventDefault();
			sub.disabled = true;
			loadState().then(function () {
				var d = new FormData(f); d.delete('manage_email'); d.append('post', pid);
				return j(api + 'reviews', { method: 'POST', body: d });
			}).then(function (r) {
				say(r.message);
				f.reset();
				if (r.html) {
					var l = $('.rcom-list'), t = document.createElement('div'); t.innerHTML = r.html;
					if (l) { l.insertBefore(t.firstChild, l.firstChild); }
				}
			}).catch(function (x) { say((x && x.message) || T.err, 1); st = null; loadState(); })
				.then(function () { sub.disabled = false; if (wid !== undefined) { window.turnstile.reset(wid); } });
		});

		$('.rcom-link', f).addEventListener('click', function () {
			var m = E.manage_email;
			if (!m.value) { m.focus(); return; }
			loadState().then(function () { return post(api, 'link', { post: pid, nonce: E.nonce.value, email: m.value }); })
				.then(function (r) { say(r.message); m.value = ''; })
				.catch(function (x) { say((x && x.message) || T.err, 1); });
		});
	});
})();
