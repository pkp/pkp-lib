{**
 * controllers/tab/settings/appearance/form/sidebar.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Form fields for configuring the sidebars
 *
 *}
{if $isSiteSidebar}
    {assign var=component value="listbuilder.admin.siteSetup.AdminBlockPluginsListbuilderHandler"}
{else}
    {assign var=component value="listbuilder.settings.BlockPluginsListbuilderHandler"}
{/if}
{capture assign=blockPluginsUrl}{url router=$smarty.const.ROUTE_COMPONENT component=$component op="fetch" escape=false}{/capture}
{load_url_in_div id="blockPluginsContainer" url=$blockPluginsUrl}
