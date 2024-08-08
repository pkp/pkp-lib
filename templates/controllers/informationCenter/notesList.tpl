{**
 * templates/controllers/informationCenter/notesList.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display submission file note list in information center.
 *}

<div id="{$notesListId}" class="pkp_notes_list">
	{foreach from=$notes item=$note}
		{assign var=noteId value=$note->id}
		{include file="controllers/informationCenter/note.tpl"}
	{/foreach}
	{if $notes->isEmpty()}
		<p class="no_notes">{translate key="informationCenter.noNotes"}</p>
	{/if}
</div>
