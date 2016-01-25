{**
 * plugins/generic/usageStats/templates/optoutBlock.tpl
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Usage statistics privacy information block.
 *}
<div class="pkp_block plugins_generic_usageStats_optout" id="usageStatsOptout">
	<span class="title">
		{translate key="plugins.generic.usageStats.optout.title"}
	</span>
	<div class="content">
		{url|assign:privacyInfoUrl router=$smarty.const.ROUTE_PAGE page="usageStats" op="privacyInformation"}
		<p>{translate key="plugins.generic.usageStats.optout.shortDesc" privacyInfo=$privacyInfoUrl}</p>
	</div>
</div>
