{**
 * controllers/tab/settings/languages/languages.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Admin/manage language settings.
 *}

{* Help Link *}
{help file="settings.md" section="website" class="pkp_help_tab"}

{if in_array(ROLE_ID_SITE_ADMIN, (array)$userRoles) && !$multipleContexts}
	{capture assign=languagesUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.admin.languages.AdminLanguageGridHandler" op="fetchGrid" escape=false}{/capture}
	{load_url_in_div id="languageGridContainer" url=$languagesUrl}
{else}
	{capture assign=languagesUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.languages.ManageLanguageGridHandler" op="fetchGrid" escape=false}{/capture}
	{load_url_in_div id="languageGridContainer" url=$languagesUrl}
{/if}
