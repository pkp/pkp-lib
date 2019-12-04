{**
 * templates/stats/editorialReport.tpl
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * The editorial statistics page.
 *
 *}
{include file="common/header.tpl" suppressPageTitle=true}

<div class="pkp_page_content">
	{assign var="uuid" value=""|uniqid|escape}
	<div id="users-stats-handler-{$uuid}" class="pkpStats pkpStats--users">
		<pkp-header id="usersTabelLabel">
			<h1>{translate key="manager.statistics.statistics.registeredUsers"}</h1>
		</pkp-header>
		<div class="pkpStats__container">
			<div class="pkpStats__content">
				<pkp-table
					labelled-by="usersTabelLabel"
					:columns="tableColumns"
					:rows="tableRows"
				></pkp-table>
			</div>
		</div>
	</div>
	<script>
		pkp.registry.init('users-stats-handler-{$uuid}', 'Container', {$statsComponentData|json_encode});
	</script>
</div>

{include file="common/footer.tpl"}
