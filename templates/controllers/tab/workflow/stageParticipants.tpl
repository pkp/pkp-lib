{**
 * templates/workflow/stageParticipants.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Include for stage participants grid.
 *}
{capture assign=stageParticipantGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.users.stageParticipant.StageParticipantGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}{/capture}
{load_url_in_div id="stageParticipantGridContainer-"|concat:$reviewRoundId url=$stageParticipantGridUrl class="pkp_participants_grid"}
