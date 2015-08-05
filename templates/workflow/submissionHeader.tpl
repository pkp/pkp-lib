{**
 * templates/workflow/submissionHeader.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Include the submission progress bar
 *}
<script type="text/javascript">
	// Initialise JS handler.
	$(function() {ldelim}
		$('#submissionHeader').pkpHandler(
			'$.pkp.pages.workflow.SubmissionHeaderHandler', {ldelim}
				participantToggleSelector: '#participantToggle'
			{rdelim}
		);
	{rdelim});
</script>
<div id="submissionHeader">
    <div class="pkp_page_title">
        <h2 class="pkp_submission_title">
            <!-- @todo screen reader text: Submission Title: -->
            {$submission->getLocalizedTitle()}
        </h2>
        <h3 class="pkp_submission_author">
            <!-- @todo screen reader text: Submission Authors: -->
            {$submission->getAuthorString()}
        </h3>
        <ul class="pkp_submission_actions">
            {if array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), $userRoles)}
                <li>
                    {include file="linkAction/linkAction.tpl" action=$submissionEntryAction}
                </li>
            {/if}
            <li>
                {include file="linkAction/linkAction.tpl" action=$submissionInformationCenterAction}
            </li>
            <li class="participants">
                <a href="javascript:$.noop();" id="participantToggle">{translate key="editor.submission.stageParticipants"}</a>
            </li>
        </ul>
    </div>

    {* This must be within the SubmissionHeaderHandler element in order for the
       participants panel to work. *}
    {url|assign:submissionProgressBarUrl op="submissionProgressBar" submissionId=$submission->getId() stageId=$stageId contextId="submission" escape=false}
    {load_url_in_div id="submissionProgressBarDiv" url=$submissionProgressBarUrl}

</div>
