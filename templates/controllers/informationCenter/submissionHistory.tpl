{**
 * templates/controllers/informationCenter/submissionHistory.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Information Center submission history tab.
 *}

{help file="editorial-workflow" section="editorial-history" class="pkp_help_tab"}

{capture assign=submissionHistoryGridUrl}{url params=$gridParameters router=$smarty.const.ROUTE_COMPONENT component="grid.eventLog.SubmissionEventLogGridHandler" op="fetchGrid" escape=false}{/capture}
{load_url_in_div id="submissionHistoryGridContainer" url=$submissionHistoryGridUrl}
