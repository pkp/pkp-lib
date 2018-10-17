{**
 * templates/controllers/grid/user/reviewer/form/advancedSearchReviewerAssignmentForm.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Assigns the reviewer (selected from the reviewerSelect grid) to review the submission.
 *
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler for second form.
		$('#advancedSearchReviewerForm').pkpHandler('$.pkp.controllers.grid.users.reviewer.form.AddReviewerFormHandler',
			{ldelim}
				templateUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component='grid.users.reviewer.ReviewerGridHandler' op='fetchTemplateBody' stageId=$stageId reviewRoundId=$reviewRoundId submissionId=$submissionId escape=false}
			{rdelim}
		);
	{rdelim});
</script>

{* The form that will create the review assignment.  A reviewer ID must be loaded in here via the grid above. *}
<form class="pkp_form" id="advancedSearchReviewerForm" method="post" action="{url op="updateReviewer"}" >
	{csrf}
	{fbvElement type="hidden" id="reviewerId" value=$reviewerId}

	{include file="controllers/grid/users/reviewer/form/reviewerFormFooter.tpl"}

	{fbvFormButtons submitText="editor.submission.addReviewer"}
</form>
