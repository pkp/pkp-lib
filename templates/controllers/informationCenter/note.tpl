{**
 * templates/controllers/informationCenter/note.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a single information center note.
 *
 *}

{* These variables are both "safe" to be used unescaped. *}
{assign var="noteId" value=$note->getId()}
{assign var="formId" value="deleteNoteForm-$noteId"}

<script type="text/javascript">
	$(function() {ldelim}
			// Attach the form handler.
			$('#{$formId}').pkpHandler('$.pkp.controllers.form.AjaxFormHandler', {ldelim}
				baseUrl: {$baseUrl|json_encode}
			{rdelim});
	{rdelim});
</script>

<div id="note-{$noteId}" class="noteWrapper pkp_helpers_dotted_underline">
	<table width="100%">
		<tr valign="top">
			<td colspan="2"{if $noteViewStatus==$smarty.const.RECORD_VIEW_RESULT_INSERTED} class="newNote"{/if}>
				{assign var="noteUser" value=$note->getUser()}
				{$noteUser->getFullName()|escape}<br />
				<span class="pkp_controllers_informationCenter_itemLastEvent">{$note->getDateCreated()|date_format:$datetimeFormatShort}</span>
			</td>
			<td class="pkp_helpers_align_right">
				{* Check that notes are deletable (i.e. not attached to files from previous stages) and the current user has permission to delete. *}
				{if $notesDeletable && array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), $userRoles)}
					<form class="pkp_form" id="{$formId}" action="{url op="deleteNote" noteId=$noteId params=$linkParams}">
						{assign var=deleteNoteButtonId value="deleteNote-$noteId"}
						{include file="linkAction/buttonConfirmationLinkAction.tpl" titleIcon="modal_delete" buttonSelector="#$deleteNoteButtonId" dialogText="informationCenter.deleteConfirm"}
						<input type="submit" id="{$deleteNoteButtonId}" class="xIcon" value="{translate key='common.delete'}" />
					</form>
				{/if}
			</td>
		</tr>
		<tr valign="top">
			<td colspan="3">
				{include file="controllers/revealMore.tpl" content=$note->getContents()|strip_unsafe_html}
				{if $noteFileDownloadLink}
					<br />
					<span class="noteFile">
						<span class="pkp_helpers_align_left"><strong>{translate key="submission.attachedFile"}:</strong></span>
						{include file="linkAction/linkAction.tpl" action=$noteFileDownloadLink contextId=$note->getId()}
					</span>
				{/if}
			</td>
		</tr>
	</table>
</div>

