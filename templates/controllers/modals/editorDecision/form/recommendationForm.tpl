{**
 * templates/controllers/modals/editorDecision/form/recommendationForm.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form used to send the editor recommendation
 *
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#recommendations').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="recommendations" method="post" action="{url op='saveRecommendation'}" >
	{csrf}
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />
	
	{if !empty($editorRecommendations)}
		{fbvFormSection label="editor.submission.recordedRecommendations"}
			{foreach from=$editorRecommendations item=editorRecommendation}
				<div>
					{translate key="submission.round" round=$editorRecommendation.round} ({$editorRecommendation.dateDecided|date_format:$datetimeFormatShort}): {translate key=$recommendationOptions[$editorRecommendation.decision]}
				</div>
			{/foreach}
		{/fbvFormSection}
	{/if}
	
	{fbvFormSection label="editor.submission.recommendation" description=$description|default:"editor.submission.recommendation.description"}
		{fbvElement type="select" id="recommendation" name="recommendation" from=$recommendationOptions selected=$recommendation size=$fbvStyles.size.MEDIUM required=$required|default:true disabled=$readOnly}
	{/fbvFormSection}
	
	{fbvFormSection title="user.role.editors" for="editors" size=$fbvStyles.size.MEDIUM}
		{fbvElement type="text" id="editors" name="editors" value=$editors disabled=true}
	{/fbvFormSection}

	{fbvFormSection title="stageParticipants.notify.message" for="personalMessage"}
		{fbvElement type="textarea" name="personalMessage" id="personalMessage" value=$personalMessage rich=true variables=$allowedVariables variablesType=$allowedVariablesType}
	{/fbvFormSection}

	{fbvFormSection for="skipEmail" size=$fbvStyles.size.MEDIUM list=true}
		{fbvElement type="checkbox" id="skipEmail" name="skipEmail" label="editor.submissionReview.recordRecommendation.skipEmail"}
	{/fbvFormSection}

	{fbvFormSection for="skipDiscussion" size=$fbvStyles.size.MEDIUM list=true}
		{fbvElement type="checkbox" id="skipDiscussion" name="skipDiscussion" label="editor.submissionReview.recordRecommendation.skipDiscussion"}
	{/fbvFormSection}

	{fbvFormButtons submitText="editor.submissionReview.recordRecommendation"}
</form>
