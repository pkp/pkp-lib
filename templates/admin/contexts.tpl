{**
 * templates/admin/contexts.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display list of contexts in administration.
 *}
{include file="common/header.tpl" pageTitle="context.contexts"}

<div class="pkp_page_content pkp_page_admin">
	{capture assign=contextsUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.admin.context.ContextGridHandler" op="fetchGrid" escape=false}{/capture}
	{load_url_in_div id="contextGridContainer" url=$contextsUrl}
</div>

{include file="common/footer.tpl"}
