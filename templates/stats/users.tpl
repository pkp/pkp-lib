{**
 * lib/pkp/templates/stats/users.tpl
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The editorial statistics page.
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<div class="pkpStats">
		<div class="pkpStats__panel">
			<pkp-header>
				<h1 id="usersTableLabel" class="pkpHeader__title">{translate key="manager.statistics.statistics.registeredUsers"}</h1>
				<template #actions>
					<pkp-button ref="exportButton" @click="openExportModal">
						{translate key="common.export"}
					</pkp-button>
				</template>
			</pkp-header>
			<pkp-table labelled-by="usersTableLabel">
				<table-header>
					<table-column>{translate key="common.name"}</table-column>
					<table-column>{translate key="stats.total"}</table-column>
				</table-header>
				<table-body>
					{foreach from=$userStats item=$row}
						<table-row>
							<table-cell>{$row.name}</table-cell>
							<table-cell>{$row.value}</table-cell>
						</table-row>
					{/foreach}
				</table-body>
			</pkp-table>
		</div>
	</div>
{/block}
