(function () {
	'use strict';
	var t = function (text, vars) { return OC.L10N.translate('docsort', text, vars); };
	var $ = function (id) { return document.getElementById(id); };
	var rules = OCP.InitialState.loadState('docsort', 'rules');
	var custom = OCP.InitialState.loadState('docsort', 'custom');
	var python = OCP.InitialState.loadState('docsort', 'python');
	function status(text, cls) { var el = $('docsort-rules-status'); el.textContent = text; el.className = 'docsort-status ' + (cls || ''); }
	function show() {
		$('docsort-rules').value = JSON.stringify(rules, null, 2);
		$('docsort-python').textContent = t('Documents are read with {python} (RapidOCR for pictures and scans, pdftotext for PDFs with text). {rules}', { python: python, rules: custom ? t('The rules below are yours.') : t('The rules below are the built-in ones.') });
	}
	function post(url, body) {
		return fetch(OC.generateUrl('/apps/docsort' + url), { method: 'POST', headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken }, body: JSON.stringify(body) })
			.then(function (r) { if (!r.ok) { throw new Error('http ' + r.status); } return r.json(); });
	}
	$('docsort-rules-save').addEventListener('click', function () {
		var parsed;
		try { parsed = JSON.parse($('docsort-rules').value); } catch (e) { status(t('This is not valid JSON: {e}', { e: e.message }), 'error'); return; }
		if (!Array.isArray(parsed)) { status(t('The rules have to be a list.'), 'error'); return; }
		status(t('Saving …'));
		post('/api/admin/rules', { rules: parsed }).then(function (d) { rules = d.rules; custom = d.custom; show(); status(t('Saved: {n} kinds of documents.', { n: rules.length }), 'ok'); }).catch(function () { status(t('Could not save.'), 'error'); });
	});
	$('docsort-rules-reset').addEventListener('click', function () {
		post('/api/admin/rules', { rules: [] }).then(function (d) { rules = d.rules; custom = d.custom; show(); status(t('Back to the built-in rules.'), 'ok'); }).catch(function () { status(t('Could not save.'), 'error'); });
	});
	$('docsort-test').addEventListener('click', function () {
		post('/api/admin/test', { text: $('docsort-test-text').value }).then(function (d) {
			$('docsort-test-result').textContent = d.kind
				? t('{kind} ({category}), score {score} of at least {min}; words: {hits}', { kind: d.kind, category: d.category, score: d.score, min: d.min, hits: d.hits.join(', ') }) + (d.runnerUp ? ' — ' + t('runner-up {kind} ({score})', { kind: d.runnerUp, score: d.runnerUpScore }) : '')
				: t('not recognised (best score {score}, needs {min}; words: {hits})', { score: d.score, min: d.min, hits: d.hits.join(', ') || '—' });
		}).catch(function () { $('docsort-test-result').textContent = t('The check failed.'); });
	});
	show();
})();
