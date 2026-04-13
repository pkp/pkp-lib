{**
 * templates/reviewer/review/step3.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Show the step 3 review page
 *}

<div id="reviewSubmissionForm3-{$uuid}">
	<review-submission-form-3
		:submission-id={$submission->getId()|json_encode}
		:submission='{$submissionForVue|json_encode}'
		save-step-url="{$saveStepUrl|escape}"
		cancel-url="{$cancelUrl|escape}"
		:stage-id={$stageId|json_encode}
		:submission-stage-id={$stageId|json_encode}
		:review-round-id={$reviewRoundId|json_encode}
		:review-assignment-id={$reviewAssignmentId|json_encode}
		{if $reviewFormData}
			:review-form='{$reviewFormData|json_encode}'
			:review-form-elements='{$reviewFormElementsData|json_encode}'
			:review-form-responses='{$reviewFormResponsesData|json_encode}'
		{/if}
		:reviewer-recommendation-options='{$reviewerRecommendationOptions|json_encode}'
		{if $selectedRecommendationId}
			:selected-recommendation-id={$selectedRecommendationId|json_encode}
		{/if}
		:comments="{$comments|json_encode|escape}"
		:comments-private="{$commentsPrivate|json_encode|escape}"
		:review-is-closed={if $reviewIsClosed}true{else}false{/if}
		{if $reviewGuidelines}
			:review-guidelines="{$reviewGuidelines|json_encode|escape}"
		{/if}
	></review-submission-form-3>
</div>

<script>
	pkp.registry.init('reviewSubmissionForm3-{$uuid}', 'Page', {ldelim}
		tinyMCE: {$tinyMCE|json_encode}
	{rdelim});
</script>
