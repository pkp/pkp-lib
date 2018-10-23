{**
 * templates/dashboard/index.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Dashboard index.
 *}
{include file="common/header.tpl" pageTitle="navigation.submissions"}

<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#dashboardTabs').pkpHandler('$.pkp.controllers.TabHandler');
	{rdelim});
</script>
<div id="dashboardTabs" class="pkp_controllers_tab">
	<ul>
		<li><a name="myQueue" href="#myQueue">{translate key="dashboard.myQueue"}</a></li>
		{if array_intersect(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER), (array)$userRoles)}
			<li><a name="unassigned" href="#unassigned">{translate key="common.queue.long.submissionsUnassigned"}</a></li>
			<li><a name="active" href="#active">{translate key="common.queue.long.active"}</a></li>
		{/if}
		<li><a name="archives" href="#archived">{translate key="navigation.archives"}</a></li>
	</ul>
	<div id="myQueue">
		{help file="submissions" class="pkp_help_tab"}
		<div class="pkp_content_panel">
			{assign var="uuid" value=""|uniqid|escape}
			<div id="my-submission-list-handler-{$uuid}">
				<script type="text/javascript">
					pkp.registry.init('my-submission-list-handler-{$uuid}', 'SubmissionsListPanel', {$myQueueListData|json_encode});
				</script>
			</div>
		</div>
	</div>
	{if array_intersect(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER), (array)$userRoles)}
		<div id="unassigned">
			{help file="submissions" section="unassigned" class="pkp_help_tab"}
			<div class="pkp_content_panel">
				{assign var="uuid" value=""|uniqid|escape}
				<div id="unassigned-list-handler-{$uuid}">
					<script type="text/javascript">
						pkp.registry.init('unassigned-list-handler-{$uuid}', 'SubmissionsListPanel', {$unassignedListData|json_encode});
					</script>
				</div>
			</div>
		</div>
		<div id="active">
			{help file="submissions" section="active" class="pkp_help_tab"}
			<div class="pkp_content_panel">
				{assign var="uuid" value=""|uniqid|escape}
				<div id="active-list-handler-{$uuid}">
					<script type="text/javascript">
						pkp.registry.init('active-list-handler-{$uuid}', 'SubmissionsListPanel', {$activeListData|json_encode});
					</script>
				</div>
			</div>
		</div>
	{/if}
	<div id="archived">
		{help file="submissions" section="archives" class="pkp_help_tab"}
		<div class="pkp_content_panel">
			{assign var="uuid" value=""|uniqid|escape}
			<div id="archived-list-handler-{$uuid}">
				<script type="text/javascript">
					pkp.registry.init('archived-list-handler-{$uuid}', 'SubmissionsListPanel', {$archivedListData|json_encode});
				</script>
			</div>
		</div>
	</div>
</div>

{include file="common/footer.tpl"}
