{**
 * templates/stats/counterReports.tpl
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Set up and download COUNTER R5 TSV reports
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.statistics.counterR5Reports"}
	</h1>
	<p>{translate key="manager.statistics.counterR5Reports.description"}</p>
	{if !$usagePossible}
		<notification class="pkpNotification--backendPage__header" type="warning">{translate key="manager.statistics.counterR5Reports.usageNotPossible"}</notification>
	{/if}
	<panel>
		<panel-section>
			<counter-reports-list-panel
				v-bind="components.counterReportsListPanel"
				@set="set"
			/>
		</panel-section>
	</panel>
{/block}
