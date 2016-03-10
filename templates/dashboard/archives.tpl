{**
 * templates/dashboard/archives.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Dashboard archived submissions tab.
 *}

{help file="chapter3/archives.md" class="pkp_helpers_align_right"}
<div class="pkp_helpers_clear"></div>

{url|assign:archivedSubmissionsListGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.archivedSubmissions.ArchivedSubmissionsListGridHandler" op="fetchGrid" escape=false}
{load_url_in_div id="archivedSubmissionsListGridContainer" url=$archivedSubmissionsListGridUrl}
