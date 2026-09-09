(function () {
	'use strict';
	var t = function (text, vars) { return OC.L10N.translate('docsort', text, vars); };
	var $ = function (id) { return document.getElementById(id); };
	var config = OCP.InitialState.loadState('docsort', 'config');
	var kinds = OCP.InitialState.loadState('docsort', 'kinds');
	var categories = OCP.InitialState.loadState('docsort', 'categories');
	var history = OCP.InitialState.loadState('docsort', 'history');
	var lang = function () { return $('docsort-language').value === 'en' ? 'en' : 'ro'; };

	function fill() {
		$('docsort-enabled').checked = !!config.enabled;
		$('docsort-inbox').value = config.inbox;
		$('docsort-destination').value = config.destination;
		$('docsort-mode').value = config.mode;
		$('docsort-bycategory').checked = !!config.byCategory;
		$('docsort-unknown').value = config.unknown;
		$('docsort-language').value = config.language;
	}
	function collect() {
		return { enabled: $('docsort-enabled').checked, inbox: $('docsort-inbox').value, destination: $('docsort-destination').value, mode: $('docsort-mode').value, byCategory: $('docsort-bycategory').checked, unknown: $('docsort-unknown').value, language: $('docsort-language').value };
	}
	function status(text, cls) { var el = $('docsort-status'); el.textContent = text; el.className = 'docsort-status ' + (cls || ''); }
	function esc(s) { return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function kindName(id) { if (!id) { return ''; } var k = kinds[id]; return k ? k[lang()] : id; }
	function categoryName(id) { var c = categories[id]; return c ? c[lang()] : (id || ''); }
	function when(ts) { return ts ? new Date(ts * 1000).toLocaleString() : ''; }

	function renderHistory(rows, dry) {
		if (!rows.length) { $('docsort-history').innerHTML = '<p class="settings-hint">' + esc(t('Nothing yet.')) + '</p>'; return; }
		var html = '<table><thead><tr><th>' + esc(t('File')) + '</th><th>' + esc(t('Kind')) + '</th><th>' + esc(t('Category')) + '</th><th>' + esc(t('Score')) + '</th><th>' + esc(dry ? t('Would go to') : t('Filed as')) + '</th><th>' + esc(t('When')) + '</th></tr></thead><tbody>';
		rows.forEach(function (r) {
			var cls = r.error ? 'error' : (r.kind ? '' : 'unknown');
			html += '<tr><td>' + esc(r.name) + '</td><td class="' + cls + '">' + esc(r.error ? t('error: {e}', { e: r.error }) : (r.kind ? kindName(r.kind) : t('not recognised'))) + '</td><td>' + esc(categoryName(r.category)) + '</td><td>' + esc(r.score) + '</td><td>' + esc(r.movedTo || '') + '</td><td>' + esc(when(r.processed)) + '</td></tr>';
		});
		$('docsort-history').innerHTML = html + '</tbody></table>';
	}

	function post(url, body) {
		return fetch(OC.generateUrl('/apps/docsort' + url), { method: 'POST', headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken }, body: JSON.stringify(body) })
			.then(function (r) { if (!r.ok) { throw new Error('http ' + r.status); } return r.json(); });
	}

	$('docsort-save').addEventListener('click', function () {
		status(t('Saving …'));
		post('/api/config', { values: collect() }).then(function (saved) { config = saved; fill(); status(t('Saved.'), 'ok'); }).catch(function () { status(t('Could not save.'), 'error'); });
	});
	function scan(dry) {
		status(dry ? t('Reading …') : t('Sorting … this can take a while, the documents are read one by one.'));
		post('/api/config', { values: collect() }).then(function () { return post('/api/scan', { dryRun: dry }); }).then(function (d) {
			status(t('{files} files read: {sorted} recognised, {unknown} not, {errors} errors.', d.stats), 'ok');
			if (dry) { renderHistory(d.results, true); } else { fetch(OC.generateUrl('/apps/docsort/api/history'), { headers: { requesttoken: OC.requestToken } }).then(function (r) { return r.json(); }).then(function (h) { renderHistory(h, false); }); }
		}).catch(function () { status(t('Sorting failed.'), 'error'); });
	}
	$('docsort-scan').addEventListener('click', function () { scan(false); });
	$('docsort-dry').addEventListener('click', function () { scan(true); });
	$('docsort-language').addEventListener('change', function () { renderHistory(history, false); });

	fill();
	renderHistory(history, false);
	$('docsort-noreader').hidden = !!OCP.InitialState.loadState('docsort', 'readerReady');
})();
