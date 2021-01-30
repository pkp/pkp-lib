{**
 * templates/admin/contexts.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display list of contexts in administration.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}

	{if $newVersionAvailable}
		<notification>
			{translate key="site.upgradeAvailable.admin" currentVersion=$currentVersion->getVersionString(false) latestVersion=$latestVersion}
		</notification>
	{/if}

	{capture assign=contextsUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.admin.context.ContextGridHandler" op="fetchGrid" escape=false}{/capture}
	{load_url_in_div id="contextGridContainer" url=$contextsUrl}

{/block}