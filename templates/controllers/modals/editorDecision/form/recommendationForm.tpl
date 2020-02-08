{**
 * templates/controllers/modals/editorDecision/form/recommendationForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form used to send the editor recommendation
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		$('#recommendations').pkpHandler(
			'$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler'
		);
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

	{capture assign="sendEmailLabel"}{translate key="editor.submissionReview.sendEmail.editors" editorNames=$editors}{/capture}
	{if $skipEmail}
		{assign var="skipEmailSkip" value=true}
	{else}
		{assign var="skipEmailSend" value=true}
	{/if}
	{fbvFormSection title="editor.submissionReview.recordRecommendation.notifyEditors"}
		<ul class="checkbox_and_radiobutton">
			{fbvElement type="radio" id="skipEmail-send" name="skipEmail" value="0" checked=$skipEmailSend label=$sendEmailLabel translate=false}
			{fbvElement type="radio" id="skipEmail-skip" name="skipEmail" value="1" checked=$skipEmailSkip label="editor.submissionReview.skipEmail"}
		</ul>
	{/fbvFormSection}

	{if $skipDiscussion}
		{assign var="skipDiscussionSkip" value=true}
	{else}
		{assign var="skipDiscussionSend" value=true}
	{/if}
	{fbvFormSection}
		<ul class="checkbox_and_radiobutton">
			{fbvElement type="radio" id="skipDiscussion-send" name="skipDiscussion" value="0" checked=$skipDiscussionSend label="editor.submissionReview.recordRecommendation.createDiscussion"}
			{fbvElement type="radio" id="skipDiscussion-skip" name="skipDiscussion" value="1" checked=$skipDiscussionSkip label="editor.submissionReview.recordRecommendation.skipDiscussion"}
		</ul>
	{/fbvFormSection}

	<div id="sendReviews-emailContent" style="margin-bottom: 30px;">
		{fbvFormSection for="personalMessage"}
			{fbvElement type="textarea" name="personalMessage" id="personalMessage" value=$personalMessage rich=true variables=$allowedVariables variablesType=$allowedVariablesType}
		{/fbvFormSection}
	</div>

	{fbvFormButtons submitText="editor.submissionReview.recordRecommendation"}
</form>
