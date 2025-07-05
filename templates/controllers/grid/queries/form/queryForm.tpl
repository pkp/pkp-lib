{**
 * templates/controllers/grid/queries/form/queryForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Query grid form
 *
 *}

 {if !count($allParticipants)}
		{translate key="submission.query.noParticipantOptions"}
 {else}
	<script>
		// Attach the handler.
		$(function() {ldelim}
			$('#queryForm').pkpHandler(
				'$.pkp.controllers.grid.queries.QueryFormHandler',
				{ldelim}
					cancelUrl: {if $isNew}{url|json_encode op="deleteQuery" queryId=$queryId csrfToken=$csrfToken params=$actionArgs escape=false}{else}null{/if},
					templateUrl: {url|json_encode router=PKP\core\PKPApplication::ROUTE_COMPONENT component='grid.queries.QueriesGridHandler' op='fetchTemplateBody' stageId=$stageId submissionId=$assocId escape=false},
				{rdelim}
			);
		{rdelim});
	</script>

	<form class="pkp_form" id="queryForm" method="post" action="{url op="updateQuery" queryId=$queryId params=$actionArgs wasNew=$isNew}">
		{csrf}

		{include file="controllers/notification/inPlaceNotification.tpl" notificationId="queryFormNotification"}

		{fbvFormSection list=true title="editor.submission.stageParticipants"}
			{foreach from=$allParticipants item="participant" key="id"}
				{fbvElement type="checkbox" id="users[]" value=$id checked=in_array($id, $assignedParticipants) label=$participant|escape translate=false}
			{/foreach}
		{/fbvFormSection}

		{if count($templates)}
			{fbvFormArea id="queryTemplateArea"}
				{fbvFormSection title="stageParticipants.notify.chooseMessage" for="template" size=$fbvStyles.size.medium}
					{fbvElement type="select" from=$templates translate=false id="template" selected=$template defaultValue="" defaultLabel=""}
				{/fbvFormSection}
			{/fbvFormArea}
		{/if}

		{fbvFormArea id="queryContentsArea"}
			{fbvFormSection title="common.subject" for="subject" required="true"}
				{fbvElement type="text" id="subject" value=$subject required="true"}
			{/fbvFormSection}

			{fbvFormSection title="stageParticipants.notify.message" for="comment" required="true"}
				{fbvElement type="textarea" id="comment" rich=true value=$comment required="true"}
			{/fbvFormSection}
		{/fbvFormArea}

		{fbvFormArea id="queryNoteFilesArea"}
			{capture assign=queryNoteFilesGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.files.query.QueryNoteFilesGridHandler" op="fetchGrid" params=$actionArgs queryId=$queryId noteId=$noteId escape=false}{/capture}
			{load_url_in_div id="queryNoteFilesGrid" url=$queryNoteFilesGridUrl}
		{/fbvFormArea}

		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

		{if $allowedEditTimeNotice['show']}
			<p><span class="sub_label">{translate key="submission.query.allowedEditTime" allowedEditTimeNoticeLimit=$allowedEditTimeNotice['limit']}</span></p>
		{/if}

		{fbvFormButtons id="addQueryButton"}

	</form>
{/if}
