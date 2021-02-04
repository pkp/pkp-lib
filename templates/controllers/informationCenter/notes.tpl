{**
 * templates/controllers/informationCenter/notes.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display submission file notes/note form in information center.
 *
 * @uses string {$notesList} Compiled template displaying notes
 * @uses string {$pastNotesList} Compiled template displaying past notes
 *}
<script type="text/javascript">
	// Attach the Notes handler.
	$(function() {ldelim}
		$('#informationCenterNotes').pkpHandler(
			'$.pkp.controllers.informationCenter.NotesHandler' );
	{rdelim});
</script>

<div id="informationCenterNotes">

	{$notesList}

	{if $showEarlierEntries}
		{include file="controllers/extrasOnDemand.tpl"
			id="showPastNotesLink"
			moreDetailsText="informationCenter.pastNotes"
			lessDetailsText="informationCenter.pastNotes"
			extraContent=$pastNotesList
		}
	{/if}

	{include file=$newNoteFormTemplate}
</div>
