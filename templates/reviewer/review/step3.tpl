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
		:review-assignment='{$reviewAssignmentData|json_encode}'
		{if $reviewFormData}
			:review-form='{$reviewFormData|json_encode}'
			:review-form-elements='{$reviewFormElementsData|json_encode}'
			:review-form-responses='{$reviewFormResponsesData|json_encode}'
		{/if}
		:reviewer-recommendation-options='{$reviewerRecommendationOptions|json_encode}'
		:comments="{$comments|json_encode|escape}"
		:comments-private="{$commentsPrivate|json_encode|escape}"
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
