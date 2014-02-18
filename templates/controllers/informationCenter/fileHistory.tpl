{**
 * templates/controllers/informationCenter/fileHistory.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display submission file history in information center.
 *}
{url|assign:eventLogGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.eventLog.SubmissionFileEventLogGridHandler" op="fetchGrid" params=$linkParams escape=false}
{load_url_in_div id="eventLogGrid" url=$eventLogGridUrl}
