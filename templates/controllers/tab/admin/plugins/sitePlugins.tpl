{**
 * templates/controllers/tab/admin/plugins/sitePlugins.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * List available plugins.
 *}
{capture assign=pluginGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.admin.plugins.AdminPluginGridHandler" op="fetchGrid" escape=false}{/capture}
{load_url_in_div id="pluginGridContainer" url=$pluginGridUrl}
