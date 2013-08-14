{**
 * templates/workflow/header.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Header that contains details about the submission
 *}
{strip}
{assign var=primaryAuthor value=$submission->getPrimaryAuthor()}
{if !$primaryAuthor}
	{assign var=authors value=$submission->getAuthors()}
	{assign var=primaryAuthor value=$authors[0]}
{/if}
{assign var=submissionTitleSafe value=$submission->getLocalizedTitle()|strip_unsafe_html}
{assign var="pageTitleTranslated" value=$primaryAuthor->getLastName()|concat:", ":$submissionTitleSafe}
{include file="common/header.tpl" suppressPageTitle=true}
{/strip}

<script type="text/javascript">
	// Initialise JS handler.
	$(function() {ldelim}
		$('#submissionWorkflow').pkpHandler(
			'$.pkp.pages.workflow.WorkflowHandler'
		);
	{rdelim});
</script>

<div id="submissionWorkflow">

{url|assign:submissionProgressBarUrl op="submissionProgressBar" submissionId=$submission->getId() stageId=$stageId contextId="submission" escape=false}
{load_url_in_div id="submissionProgressBarDiv" url=$submissionProgressBarUrl class="submissionProgressBar"}

{include file="controllers/notification/inPlaceNotification.tpl" notificationId="workflowNotification" requestOptions=$workflowNotificationRequestOptions}
<br />
<div class="pkp_helpers_clear"></div>
