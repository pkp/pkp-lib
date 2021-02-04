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
				<template slot="actions">
					<pkp-button ref="exportButton" @click="$modal.show('export')">
						{translate key="common.export"}
					</pkp-button>
				</template>
			</pkp-header>
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
	<modal
		v-bind="MODAL_PROPS"
		name="export"
		@closed="setFocusToRef('exportButton')"
	>
		<modal-content
			close-label="common.close"
			modal-name="export"
			title="{translate key="manager.export.usersToCsv.label"}"
		>
			<pkp-form v-bind="components.usersReportForm" @set="set" @success="loadExport" />
		</modal-content>
	</modal>
{/block}
