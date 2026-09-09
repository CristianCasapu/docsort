(function () {
	'use strict';
	var t = function (text, vars) { return OC.L10N.translate('docsort', text, vars); };
	var $ = function (id) { return document.getElementById(id); };
	var rules = OCP.InitialState.loadState('docsort', 'rules');
	var custom = OCP.InitialState.loadState('docsort', 'custom');
	var python = OCP.InitialState.loadState('docsort', 'python');
	var reader = OCP.InitialState.loadState('docsort', 'reader');
	var pollTimer = null;
	function status(text, cls) { var el = $('docsort-rules-status'); el.textContent = text; el.className = 'docsort-status ' + (cls || ''); }
	function show() {
		$('docsort-rules').value = JSON.stringify(rules, null, 2);
		$('docsort-python').textContent = custom ? t('The rules below are yours.') : t('The rules below are the built-in ones.');
	}
	function post(url, body) {
		return fetch(OC.generateUrl('/apps/docsort' + url), { method: 'POST', headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken }, body: JSON.stringify(body) })
			.then(function (r) { if (!r.ok) { throw new Error('http ' + r.status); } return r.json(); });
	}
	function call(method, url) {
		return fetch(OC.generateUrl('/apps/docsort' + url), { method: method, headers: { requesttoken: OC.requestToken } })
			.then(function (r) { return r.json().then(function (d) { if (!r.ok && !d.error) { throw new Error('http ' + r.status); } return d; }); });
	}

	function showReader() {
		var st = reader.reader, ins = st.install, el = $('docsort-reader-status'), log = $('docsort-reader-log'), note = $('docsort-reader-note');
		var busy = ins.state === 'queued' || ins.state === 'running';
		var text;
		if (st.installed) {
			text = t('The reader is installed: {python}', { python: st.python });
		} else if (reader.external) {
			text = t('Documents are read with {python} (a reader outside the app); the app can install its own instead.', { python: reader.python });
		} else if (!st.canInstall) {
			text = st.reason;
		} else {
			text = t('The reader is not installed yet: pictures and PDFs are not read until it is. Python {v} at {p} will build it.', { v: st.systemPythonVersion, p: st.systemPython });
		}
		el.textContent = text;
		el.className = st.installed || reader.external ? 'docsort-ok' : (st.canInstall ? 'docsort-warn' : 'docsort-error');
		$('docsort-reader-install').hidden = st.installed || !st.canInstall || busy;
		$('docsort-reader-install').textContent = ins.state === 'failed' ? t('Try again') : t('Install the reader');
		$('docsort-reader-remove').hidden = !st.installed || busy;
		if (ins.state === 'queued') { note.textContent = t('Waiting for the background job to start (usually within five minutes) …'); note.className = 'docsort-status'; }
		else if (ins.state === 'running') { note.textContent = t('Installing: {step} …', { step: ins.step }); note.className = 'docsort-status'; }
		else if (ins.state === 'failed') { note.textContent = t('The installation failed: {error}', { error: ins.error }); note.className = 'docsort-status error'; }
		else if (ins.state === 'done' && st.installed) { note.textContent = t('Installed.'); note.className = 'docsort-status ok'; }
		else { note.textContent = ''; }
		log.hidden = !ins.log || (!busy && ins.state !== 'failed');
		log.textContent = ins.log || '';
		if (busy) {
			if (!pollTimer) { pollTimer = setInterval(function () { call('GET', '/api/admin/reader').then(function (d) { reader = d; showReader(); }); }, 4000); }
		} else if (pollTimer) { clearInterval(pollTimer); pollTimer = null; show(); }
	}
	$('docsort-reader-install').addEventListener('click', function () {
		$('docsort-reader-note').textContent = t('Starting …');
		call('POST', '/api/admin/reader').then(function (d) { reader = d; if (d.error) { $('docsort-reader-note').textContent = d.error; } showReader(); }).catch(function () { $('docsort-reader-note').textContent = t('Could not start the installation.'); });
	});
	$('docsort-reader-remove').addEventListener('click', function () {
		if (!window.confirm(t('Remove the reader and install it again?'))) { return; }
		call('DELETE', '/api/admin/reader').then(function (d) { reader = d; showReader(); });
	});
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
	showReader();
})();
