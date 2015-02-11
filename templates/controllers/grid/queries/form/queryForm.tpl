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
		$('#editQuery').pkpHandler(
			'$.pkp.controllers.grid.queries.form.QueryFormHandler',
			{ldelim}
				deleteUrl: '{url|escape:javascript op="cancelQuery" submissionId=$submissionId stageId=$stageId queryId=$queryId escape=false}',
				queryId: '{$queryId|escape}'
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="editQuery" method="post" action="{url op="updateQuery" queryId=$queryId">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="queryFormNotification"}

		{fbvFormArea id="notifyFormArea"}
			{url|assign:notifyUsersUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.users.NotifyUsersListbuilderHandler" op="fetch" params=$linkParams submissionId=$submissionId userId=$userId escape=false}
			{load_url_in_div id="notifyUsersContainer" url=$notifyUsersUrl}

			{fbvFormSection title="common.subject" for="subject" required="true" size=$fbvStyles.size.medium}
				{fbvElement type="text" id="subject" multilingual="true"}
			{/fbvFormSection}

			{fbvFormSection title="stageParticipants.notify.message" for="comment" required="true"}
				{fbvElement type="textarea" id="comment" multilingual="true" rich=true}
			{/fbvFormSection}
			{fbvFormButtons id="addQueryButton"}
		{/fbvFormArea}

	<div id="filesAccordion">
		<h3>{translate key="editor.submissionReview.restrictFiles"}</h3>
		<div>
			<!-- Files for this query -->
			{url|assign:queryFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.query.QueryFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId queryId=$queryId escape=false}
			{load_url_in_div id="queryFilesGrid" url=$queryFilesGridUrl}
		</div>
	</div>

	{if $submissionId}
		<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	{/if}
		{if $stageId}
		<input type="hidden" name="stageId" value="{$stageId|escape}" />
	{/if}
	{if $gridId}
		<input type="hidden" name="gridId" value="{$gridId|escape}" />
	{/if}
	{if $rowId}
		<input type="hidden" name="rowId" value="{$rowId|escape}" />
	{/if}

</form>
<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
