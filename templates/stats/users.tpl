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
{include file="common/header.tpl" suppressPageTitle=true pageTitle="stats.userStatistics"}

<div class="pkp_page_content">
	<h1 id="usersTableLabel" class="pkpHeader__title">{translate key="manager.statistics.statistics.registeredUsers"}</h1>
	<div class="pkpStats__container">
		<div class="pkpStats__content">
			<table class="pkpTable" labelled-by="usersTableLabel">
				<thead>
					<tr>
						<th>{translate key="common.name"}</th>
						<th>{translate key="stats.total"}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$userStats item=$row}
						<tr>
							<td>{$row.name}</td>
							<td>{$row.value}</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		</div>
	</div>
</div>

{include file="common/footer.tpl"}
