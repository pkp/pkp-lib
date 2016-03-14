{**
 * controllers/tab/settings/languages/languages.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Admin/manage language settings.
 *}
{help file="chapter6/website/languages.md" class="pkp_helpers_align_right"}
<div class="pkp_helpers_clear"></div>

{if in_array(ROLE_ID_SITE_ADMIN, $userRoles) && !$multipleContexts}
	{url|assign:languagesUrl router=$smarty.const.ROUTE_COMPONENT component="grid.admin.languages.AdminLanguageGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="languageGridContainer" url=$languagesUrl}
{else}
	{url|assign:languagesUrl router=$smarty.const.ROUTE_COMPONENT component="grid.settings.languages.ManageLanguageGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="languageGridContainer" url=$languagesUrl}
{/if}
