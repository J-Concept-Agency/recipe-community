(function () {
	'use strict';
	var $ = function (s, r) { return (r || document).querySelector(s); };
	var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
	var I = {}; try { I = JSON.parse($('#rcom-i18n').textContent); } catch (x) {}
	var T = I.rv || {}, api = I.api || '';
	var j = function (u, o) {
		return fetch(u, Object.assign({ credentials: 'same-origin' }, o)).then(function (r) {
			return r.json().then(function (d) { if (!r.ok) { throw d; } return d; });
		});
	};
	var post = function (path, o) { var f = new FormData(); Object.keys(o).forEach(function (k) { f.append(k, o[k]); }); return j(api + path, { method: 'POST', body: f }); };
	var gone = function (n) { n.parentNode.removeChild(n); };
	var pid = function (el) { var s = el.closest('[data-post]'); return s ? s.dataset.post : ''; };

	$$('.rcom-list').forEach(function (l) {
		var b = $('.rcom-more', l), live = $('.rcom-sr', l);
		if (!b) { return; }
		b.addEventListener('click', function (ev) {
			ev.preventDefault();
			if (b.getAttribute('aria-disabled')) { return; }
			b.setAttribute('aria-disabled', 'true');
			var ul = $('.rcom-items', l), items = $$('.rcom-item', l), last = items[items.length - 1];
			j(api + 'reviews?post=' + pid(l) + '&limit=10&cursor=' + encodeURIComponent(last ? last.dataset.c : '')).then(function (d) {
				var t = document.createElement('div'); t.innerHTML = d.html;
				var fresh = t.firstChild;
				while (t.firstChild) { ul.appendChild(t.firstChild); }
				var left = +l.dataset.total - $$('.rcom-item', l).length;
				if (!d.count || left <= 0) { gone(b); } else {
					b.textContent = (T.more || '%s').replace('%s', left.toLocaleString());
					b.removeAttribute('aria-disabled');
				}
				if (fresh) { fresh.tabIndex = -1; fresh.focus({ preventScroll: true }); }
				if (live) { live.textContent = ''; setTimeout(function () { live.textContent = T.loaded || ''; }, 50); }
				document.dispatchEvent(new Event('rcom:loaded'));
			}).catch(function () { b.removeAttribute('aria-disabled'); });
		});
	});

	$$('.rcom-form').forEach(function (f) {
		var post_id = pid(f), msg = $('.rcom-msg', f), st, S = {}, wid, tsReady, E = f.elements;
		var say = function (m, err) { msg.textContent = m; msg.className = 'rcom-msg' + (err ? ' err' : ''); };
		var q = /[?&]rcom=(confirmed|manage)/.exec(location.search);
		if (q && T[q[1]]) { say(T[q[1]]); }

		function mark(d) {
			d.own.forEach(function (id) {
				$$('.rcom-item[data-id="' + id + '"]').forEach(function (it) {
					if ($('.rcom-del', it)) { return; }
					var h = $('.rcom-head', it), l = document.createElement('span'), b = document.createElement('button');
					l.className = 'rcom-own'; l.textContent = T.mine;
					b.type = 'button'; b.className = 'rcom-del'; b.textContent = T.del;
					b.onclick = function () {
						if (!confirm(T.sure)) { return; }
						post('delete', { id: id, nonce: S.nonce }).then(function (r) { gone(it); say(r.message); }).catch(function (x) { say(x.message || T.err, 1); });
					};
					h.appendChild(l); h.appendChild(b);
				});
			});
		}

		function loadState() {
			return st || (st = j(api + 'state?post=' + post_id).then(function (d) {
				S = d;
				mark(d);
				if (d.own.length) {
					$$(':scope > *', f).forEach(function (c) { if (!c.classList.contains('rcom-msg')) { c.hidden = true; } });
					var n = document.createElement('p'); n.className = 'rcom-small'; n.textContent = T.done;
					f.appendChild(n);
				} else if (d.me) {
					var e = $('.rcom-email', f); e.hidden = true; E.email.required = false; E.email.value = '';
					if (!E.name.value) { E.name.value = d.me.name; }
					var p = document.createElement('p'); p.className = 'rcom-small';
					p.textContent = T.as.replace('%s', d.me.hint) + ' ';
					var b = document.createElement('button'); b.type = 'button'; b.className = 'rcom-del'; b.textContent = T.notyou;
					b.onclick = function () { post('forget', {}).then(function () { gone(p); e.hidden = false; E.email.required = true; }); };
					p.appendChild(b); e.parentNode.parentNode.insertBefore(p, e.parentNode);
				}
			}).catch(function () { st = null; }));
		}

		function turnstile() {
			if (!f.dataset.ts || tsReady) { return; }
			tsReady = 1;
			var box = document.createElement('div'); box.className = 'rcom-ts';
			f.insertBefore(box, $('button[type=submit]', f).parentNode);
			var s = document.createElement('script');
			s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'; s.async = true;
			s.onload = function () { wid = window.turnstile.render(box, { sitekey: f.dataset.ts }); };
			document.head.appendChild(s);
		}
		function init() { loadState(); turnstile(); }
		f.addEventListener('focusin', init, { once: true });
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (e) { if (e[0].isIntersecting) { io.disconnect(); init(); } }, { rootMargin: '200px' });
			io.observe(f);
		} else { init(); }
		// own review controls also when the form is far away: the list can be in view first
		var li = $('.rcom-list');
		if (li && 'IntersectionObserver' in window) {
			var io2 = new IntersectionObserver(function (e) { if (e[0].isIntersecting) { io2.disconnect(); loadState(); } }, { rootMargin: '200px' });
			io2.observe(li);
		}

		var fi = $('.rcom-file', f), pv = $('.rcom-prev', f), pe = $('.rcom-ferr', f), pt = $('.rcom-fbtn-t', f), url;
		function clearPhoto() {
			fi.value = ''; pv.hidden = true; pe.textContent = ''; pt.textContent = T.padd;
			if (url) { URL.revokeObjectURL(url); url = null; }
		}
		fi.addEventListener('change', function () {
			var x = fi.files[0];
			if (!x) { clearPhoto(); return; }
			if (!/^image\/(jpeg|png|webp)$/.test(x.type) || x.size > 8388608) {
				var m = /^image\/(jpeg|png|webp)$/.test(x.type) ? T.pbig : T.ptype;
				clearPhoto(); pe.textContent = m; return;
			}
			pe.textContent = '';
			if (url) { URL.revokeObjectURL(url); }
			url = URL.createObjectURL(x);
			$('img', pv).src = url;
			var n = x.name.length > 24 ? x.name.slice(0, 14) + '…' + x.name.slice(-8) : x.name;
			$('.rcom-pn', pv).textContent = n + ' · ' + (x.size > 1048576 ? (x.size / 1048576).toFixed(1) + ' MB' : Math.round(x.size / 1024) + ' KB');
			pv.hidden = false; pt.textContent = T.pchange;
		});
		$('.rcom-del', pv).addEventListener('click', function () { clearPhoto(); fi.focus(); });
		f.addEventListener('reset', function () { setTimeout(clearPhoto, 0); });

		var sub = $('button[type=submit]', f);
		f.addEventListener('submit', function (ev) {
			ev.preventDefault();
			sub.disabled = true;
			loadState().then(function () {
				var d = new FormData(f); d.append('post', post_id); d.append('nonce', S.nonce); d.append('stamp', S.stamp);
				return j(api + 'reviews', { method: 'POST', body: d });
			}).then(function (r) {
				say(r.message);
				f.reset();
				if (r.html) {
					var l = $('.rcom-list'), ul = l && $('.rcom-items', l), t = document.createElement('div'); t.innerHTML = r.html;
					if (l && !ul) { ul = document.createElement('ul'); ul.className = 'rcom-items'; l.insertBefore(ul, l.firstChild); }
					if (ul) { ul.insertBefore(t.firstChild, ul.firstChild); }
				}
			}).catch(function (x) { say((x && x.message) || T.err, 1); st = null; loadState(); })
				.then(function () { sub.disabled = false; if (wid !== undefined) { window.turnstile.reset(wid); } });
		});
	});
})();
