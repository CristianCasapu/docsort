<?php
declare(strict_types=1);
\OCP\Util::addStyle('docsort', 'docsort');
\OCP\Util::addScript('docsort', 'personal');
/** @var \OCP\IL10N $l */
?>
<div id="docsort-personal" class="section docsort">
	<h2><?php p($l->t('Document sorter')); ?></h2>
	<p class="settings-hint"><?php p($l->t('Put scans, photos and PDFs of your papers into the inbox folder. Every quarter of an hour they are read, recognised (identity card, birth certificate, invoice, contract …), tagged and filed into folders by kind. The text is never stored, only what the document is.')); ?></p>
	<p><label class="docsort-check"><input type="checkbox" id="docsort-enabled"> <?php p($l->t('Sort my documents')); ?></label></p>
	<div class="docsort-field"><label for="docsort-inbox"><?php p($l->t('Inbox folders (one per line, relative to your files)')); ?></label><textarea id="docsort-inbox" rows="2"></textarea></div>
	<div class="docsort-field"><label for="docsort-destination"><?php p($l->t('Where sorted documents go')); ?></label><input type="text" id="docsort-destination"></div>
	<div class="docsort-field"><label for="docsort-mode"><?php p($l->t('What to do with a recognised document')); ?></label>
		<select id="docsort-mode">
			<option value="move"><?php p($l->t('Move it into the folder of its kind')); ?></option>
			<option value="copy"><?php p($l->t('Copy it there, keep the original in the inbox')); ?></option>
			<option value="tag"><?php p($l->t('Only tag it, leave it where it is')); ?></option>
		</select></div>
	<p><label class="docsort-check"><input type="checkbox" id="docsort-bycategory"> <?php p($l->t('A folder per category, then per kind (Documents / Identity / Identity card)')); ?></label></p>
	<div class="docsort-field"><label for="docsort-unknown"><?php p($l->t('Documents that are not recognised')); ?></label>
		<select id="docsort-unknown">
			<option value="leave"><?php p($l->t('stay in the inbox (tagged "Document: ?")')); ?></option>
			<option value="unknown"><?php p($l->t('go to an "Unsorted" folder')); ?></option>
		</select></div>
	<div class="docsort-field"><label for="docsort-language"><?php p($l->t('Folder and tag names')); ?></label>
		<select id="docsort-language"><option value="ro">Română</option><option value="en">English</option></select></div>
	<p>
		<button type="button" class="button primary" id="docsort-save"><?php p($l->t('Save')); ?></button>
		<button type="button" class="button" id="docsort-scan"><?php p($l->t('Sort now')); ?></button>
		<button type="button" class="button" id="docsort-dry"><?php p($l->t('Only show what would happen')); ?></button>
		<span id="docsort-status" class="docsort-status" role="status"></span>
	</p>
	<h3><?php p($l->t('Recently sorted')); ?></h3>
	<div id="docsort-history"></div>
</div>
