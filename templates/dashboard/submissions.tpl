{**
 * templates/dashboard/submissions.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Dashboard submissions tab.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#contextSubmissionForm').pkpHandler('$.pkp.controllers.dashboard.form.DashboardTaskFormHandler',
			{ldelim}
				{if $contextCount == 1}
					singleContextSubmissionUrl: '{url context=$context->getPath() page="submission" op="wizard"}',
				{/if}
				trackFormChanges: false
			{rdelim}
		);
	{rdelim});
</script>
<br />
<form class="pkp_form" id="contextSubmissionForm">
<!-- New Submission entry point -->
	{if $contextCount > 1}
		{fbvFormSection title="submission.submit.newSubmissionMultiple"}
			{capture assign="defaultLabel"}{translate key="context.select"}{/capture}
			{fbvElement type="select" id="multipleContext" from=$contexts defaultValue=0 defaultLabel=$defaultLabel translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
	{elseif $contextCount == 1}
		{fbvFormSection}
			{capture assign="singleLabel"}{translate key="submission.submit.newSubmissionSingle" contextName=$context->getLocalizedName()}{/capture}
			{fbvElement type="button" id="singleContext" label=$singleLabel translate=false}
		{/fbvFormSection}
	{/if}

</form>
<div class="pkp_helpers_clear"></div>

<!-- Author and editor submissions grid -->
{if array_intersect(array(ROLE_ID_AUTHOR, ROLE_ID_MANAGER, ROLE_ID_GUEST_EDITOR, ROLE_ID_SUB_EDITOR), $userRoles)}
	{url|assign:mySubmissionsListGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.mySubmissions.MySubmissionsListGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="mySubmissionsListGridContainer" url=$mySubmissionsListGridUrl}
{/if}

<!-- Unassigned submissions grid: If the user is a manager or a series editor, then display these submissions which have not been assigned to anyone -->
{if array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_GUEST_EDITOR), $userRoles)}
	{url|assign:unassignedSubmissionsListGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.unassignedSubmissions.UnassignedSubmissionsListGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="unassignedSubmissionsListGridContainer" url=$unassignedSubmissionsListGridUrl}
{/if}

<!-- Assigned submissions grid: Show all submissions the user is assigned to (besides their own) -->
{url|assign:assignedSubmissionsListGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.assignedSubmissions.AssignedSubmissionsListGridHandler" op="fetchGrid" escape=false}
{load_url_in_div id="assignedSubmissionsListGridContainer" url=$assignedSubmissionsListGridUrl}

<!-- Active submissions grid -->
{url|assign:activeSubmissionsListGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.activeSubmissions.ActiveSubmissionsListGridHandler" op="fetchGrid" escape=false}
{load_url_in_div id="activeSubmissionsListGridContainer" url=$activeSubmissionsListGridUrl}
