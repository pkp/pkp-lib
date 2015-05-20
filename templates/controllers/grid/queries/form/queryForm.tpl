{**
 * templates/controllers/grid/queries/form/queryForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Query grid form
 *
 *}

<script>
	// Attach the handler.
	$(function() {ldelim}
		$('#queryForm').pkpHandler(
			'$.pkp.controllers.grid.queries.form.QueryFormHandler',
			{ldelim}
				deleteUrl: {if $isNew}'{url|escape:javascript op="deleteQuery" submissionId=$submissionId stageId=$stageId queryId=$queryId escape=false}'{else}null{/if}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="queryForm" method="post" action="{url op="updateQuery"}">
	<input type="hidden" name="queryId" value="{$queryId|escape}" />
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="queryFormNotification"}

	{fbvFormArea id="queryUsersArea"}
		{url|assign:notifyUsersUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.users.StageUsersListbuilderHandler" op="fetch" params=$linkParams submissionId=$submissionId userIds=$userIds escape=false}
		{load_url_in_div id="notifyUsersContainer" url=$notifyUsersUrl}
	{/fbvFormArea}

	{fbvFormArea id="queryContentsArea"}
		{fbvFormSection title="common.subject" for="subject" required="true"}
			{fbvElement type="text" id="subject" value=$subject}
		{/fbvFormSection}

		{fbvFormSection title="stageParticipants.notify.message" for="comment" required="true"}
			{fbvElement type="textarea" id="comment" rich=true value=$comment}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="queryFilesArea"}
		<!-- Files for this query -->
		{url|assign:queryFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.query.QueryFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId queryId=$queryId escape=false}
		{load_url_in_div id="queryFilesGrid" url=$queryFilesGridUrl}
	{/fbvFormArea}

	{fbvFormButtons id="addQueryButton"}
</form>
<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
