{**
 * templates/manager/categories.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Press management categories list.
 *}

{* Help Link *}
{help file="settings.md" section="context" class="pkp_help_tab"}

{capture assign=categoriesUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.category.CategoryCategoryGridHandler" op="fetchGrid" escape=false}{/capture}
{load_url_in_div id="categoriesContainer" url=$categoriesUrl}
