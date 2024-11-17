{**
 * templates/controllers/informationCenter/note.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display a single information center note.
 *
 *}

{* These variables are both "safe" to be used unescaped. *}
{assign var="noteId" value=$note->id}
{assign var="formId" value="deleteNoteForm-$noteId"}

<script type="text/javascript">
	$(function() {ldelim}
			// Attach the form handler.
			$('#{$formId}').pkpHandler('$.pkp.controllers.form.AjaxFormHandler', {ldelim}
				baseUrl: {$baseUrl|json_encode}
			{rdelim});
	{rdelim});
</script>

<div id="note-{$noteId}" class="note">
	<div class="details">
		<span class="user">
			{assign var=noteUser value=$note->user}
			{$noteUser->getFullName()|escape}
		</span>
		<span class="date">
			{$note->dateCreated|date_format:$datetimeFormatShort}
		</span>
		{if ($notesDeletable && array_intersect(array(PKP\security\Role::ROLE_ID_MANAGER, PKP\security\Role::ROLE_ID_SUB_EDITOR), (array)$userRoles))}
			<div class="actions">
				{if $notesDeletable && array_intersect(array(PKP\security\Role::ROLE_ID_MANAGER, PKP\security\Role::ROLE_ID_SUB_EDITOR), (array)$userRoles)}
					<form class="pkp_form" id="{$formId}" action="{url op="deleteNote" noteId=$noteId params=$linkParams}">
						{csrf}
						{assign var=deleteNoteButtonId value="deleteNote-$noteId"}
						{include file="linkAction/buttonConfirmationLinkAction.tpl" modalStyle="negative" buttonSelector="#$deleteNoteButtonId" dialogText="informationCenter.deleteConfirm"}
						<button type="submit" id="{$deleteNoteButtonId}" class="pkp_button pkp_button_offset">{translate key='common.delete'}</button>
					</form>
				{/if}
			</div>
		{/if}
	</div>
	<div class="message">
		{if $noteFileDownloadLink}
			{include file="linkAction/linkAction.tpl" action=$noteFileDownloadLink contextId=$note->id}
		{/if}
		{include file="controllers/revealMore.tpl" content=$note->contents|strip_unsafe_html}
	</div>
</div>
