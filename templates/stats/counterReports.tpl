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
	<counter-reports-page v-bind="pageInitConfig" />
{/block}
