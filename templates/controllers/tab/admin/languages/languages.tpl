{**
 * controllers/tab/admin/languages/languages.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Language administration.
 *}
{capture assign=languagesUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.admin.languages.AdminLanguageGridHandler" op="fetchGrid" escape=false}{/capture}
{load_url_in_div id="languageGridContainer" url=$languagesUrl}
