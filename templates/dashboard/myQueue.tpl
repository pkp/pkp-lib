{**
 * templates/dashboard/myQueue.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User related submissions tab.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#contextSubmissionForm').pkpHandler('$.pkp.controllers.dashboard.form.DashboardTaskFormHandler',
			{ldelim}
				singleContextSubmissionUrl: {url|json_encode page="submission" op="wizard" escape=false},
				trackFormChanges: false
			{rdelim}
		);
	{rdelim});
</script>
<div class="pkp_context_sidebar">
	<div class="pkp_tab_actions">
		<form id="contextSubmissionForm">
			<ul>
				<li>
					{fbvElement type="button" id="singleContext" label="submission.submit.newSubmissionSingle"}
				</li>
			</ul>
		</form>
	</div>
	{help file="chapter3/my-queue.md"}
</div>

<div class="pkp_content_panel">
	<!-- Unassigned submissions grid: If the user is a manager or a series editor, then display these submissions which have not been assigned to anyone -->
	{if array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_GUEST_EDITOR), $userRoles)}
		{url|assign:unassignedSubmissionsListGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.unassignedSubmissions.UnassignedSubmissionsListGridHandler" op="fetchGrid" escape=false}
		{load_url_in_div id="unassignedSubmissionsListGridContainer" url=$unassignedSubmissionsListGridUrl}
	{/if}

	<!-- Assigned submissions grid: Show all submissions the user is assigned to (besides their own) -->
	{url|assign:assignedSubmissionsListGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.assignedSubmissions.AssignedSubmissionsListGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="assignedSubmissionsListGridContainer" url=$assignedSubmissionsListGridUrl}

	<!-- Author and editor submissions grid -->
	{if array_intersect(array(ROLE_ID_AUTHOR, ROLE_ID_MANAGER, ROLE_ID_GUEST_EDITOR, ROLE_ID_SUB_EDITOR), $userRoles)}
		{url|assign:mySubmissionsListGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.mySubmissions.MySubmissionsListGridHandler" op="fetchGrid" escape=false}
		{load_url_in_div id="mySubmissionsListGridContainer" url=$mySubmissionsListGridUrl}
	{/if}
</div>
