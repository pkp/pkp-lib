{**
 * templates/dashboard/active.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Dashboard active submissions tab.
 *}
<!-- Active submissions grid -->
{url|assign:activeSubmissionsListGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.activeSubmissions.ActiveSubmissionsListGridHandler" op="fetchGrid" escape=false}
{load_url_in_div id="activeSubmissionsListGridContainer" url=$activeSubmissionsListGridUrl}
