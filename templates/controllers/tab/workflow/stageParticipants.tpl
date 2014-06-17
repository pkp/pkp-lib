{**
 * templates/workflow/stageParticipants.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Include for stage participants grid.
 *}
<div class="participant_popover" style="display: none;">
	{url|assign:stageParticipantGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.users.stageParticipant.StageParticipantGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}
	{load_url_in_div id="stageParticipantGridContainer" url=$stageParticipantGridUrl}
</div>
