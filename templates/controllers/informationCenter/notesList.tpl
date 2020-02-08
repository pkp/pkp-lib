{**
 * templates/controllers/informationCenter/notesList.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display submission file note list in information center.
 *}

<div id="{$notesListId}" class="pkp_notes_list">
	{iterate from=notes item=note}
		{assign var=noteId value=$note->getId()}
		{assign var=noteViewStatus value=$note->markViewed($currentUserId)}
		{include file="controllers/informationCenter/note.tpl" noteViewStatus=$noteViewStatus}
	{/iterate}
	{if $notes->wasEmpty()}
		<p class="no_notes">{translate key="informationCenter.noNotes"}</p>
	{/if}
</div>
