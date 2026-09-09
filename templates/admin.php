<?php
declare(strict_types=1);
\OCP\Util::addStyle('docsort', 'docsort');
\OCP\Util::addScript('docsort', 'admin');
/** @var \OCP\IL10N $l */
?>
<div id="docsort-admin" class="section docsort">
	<h2><?php p($l->t('Document sorter')); ?></h2>
	<p class="settings-hint"><?php p($l->t('How a document is recognised: every kind has a list of words with weights; the words found in the text add up, and the kind with the highest score wins if it reaches its minimum. Words are matched without diacritics or case, as whole words. Edit the rules below as JSON (id, category, en, ro, min, keywords), or go back to the built-in ones.')); ?></p>
	<h3><?php p($l->t('Document reader')); ?></h3>
	<p class="settings-hint"><?php p($l->t('Pictures and PDFs are read by a small Python program (RapidOCR — neural text recognition — and pypdfium2) that the app installs by itself into the data directory, about 150 MB. Office files and plain text need nothing.')); ?></p>
	<p id="docsort-reader-status"></p>
	<pre id="docsort-reader-log" class="docsort-log" hidden></pre>
	<p>
		<button type="button" class="button primary" id="docsort-reader-install"><?php p($l->t('Install the reader')); ?></button>
		<button type="button" class="button" id="docsort-reader-remove" hidden><?php p($l->t('Remove and install again')); ?></button>
		<span id="docsort-reader-note" class="docsort-status" role="status"></span>
	</p>
	<h3><?php p($l->t('Rules')); ?></h3>
	<p class="settings-hint" id="docsort-python"></p>
	<textarea id="docsort-rules" rows="24" spellcheck="false"></textarea>
	<p>
		<button type="button" class="button primary" id="docsort-rules-save"><?php p($l->t('Save rules')); ?></button>
		<button type="button" class="button" id="docsort-rules-reset"><?php p($l->t('Back to the built-in rules')); ?></button>
		<span id="docsort-rules-status" class="docsort-status" role="status"></span>
	</p>
	<h3><?php p($l->t('Try the rules')); ?></h3>
	<p class="settings-hint"><?php p($l->t('Paste the text of a document; you see what it would be sorted as and which words decided. Nothing is stored.')); ?></p>
	<textarea id="docsort-test-text" rows="5"></textarea>
	<p><button type="button" class="button" id="docsort-test"><?php p($l->t('Classify')); ?></button> <span id="docsort-test-result"></span></p>
</div>
