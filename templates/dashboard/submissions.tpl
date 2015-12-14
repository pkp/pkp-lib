{**
 * templates/dashboard/submissions.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Dashboard user related submissions tab.
 *
 * @uses $context
 *}
<ul class="pkp_context_panel">
	<li class="pkp_context_actions">
		<ul>
			<li>
				<a href="{url context=$context->getPath() page="submission" op="wizard" escape=false}" class="new_submission">
					{translate key="submission.submit.newSubmissionSingle"}
				</a>
			</li>
		</ul>
	</li>
</ul>

<div class="pkp_content_panel">
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
</div>
