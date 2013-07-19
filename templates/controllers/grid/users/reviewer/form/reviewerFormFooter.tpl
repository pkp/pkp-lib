{**
 * templates/controllers/grid/user/reviewer/form/reviewerFormFooter.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * The non-searching part of the add reviewer form
 *
 *}
<script type="text/javascript">
	$("input[id^='responseDueDate']").datepicker({ldelim} dateFormat: 'yy-mm-dd' {rdelim});
	$("input[id^='reviewDueDate']").datepicker({ldelim} dateFormat: 'yy-mm-dd' {rdelim});
	$("#filesAccordion").accordion({ldelim}
			collapsible: true,
			active: false,
			// WARNING: The following two options are deprecated in JQueryUI.
			autoHeight: false,
			clearStyle: true
			{rdelim});
</script>

<div class="reviewerFormFooterContainer">
	<!--  Message to reviewer textarea -->
	{fbvFormSection title="editor.review.personalMessageToReviewer" for="personalMessage"}
		{fbvElement type="textarea" name="personalMessage" id="personalMessage" value=$personalMessage}
	{/fbvFormSection}

	<!-- skip email checkbox -->
	{fbvFormSection for="skipEmail" size=$fbvStyles.size.MEDIUM list=true}
		{fbvElement type="checkbox" id="skipEmail" name="skipEmail" label="editor.review.skipEmail"}
	{/fbvFormSection}

	<!--  Reviewer due dates (see http://jqueryui.com/demos/datepicker/) -->
	{fbvFormSection title="editor.review.importantDates"}
		{fbvElement type="text" id="responseDueDate" name="responseDueDate" label="submission.task.responseDueDate" value=$responseDueDate inline=true size=$fbvStyles.size.MEDIUM}
		{fbvElement type="text" id="reviewDueDate" name="reviewDueDate" label="editor.review.reviewDueDate" value=$reviewDueDate inline=true size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	<div id="filesAccordion">
		<h3>{translate key="editor.submissionReview.restrictFiles"}</h3>
		<div>
			<!-- Available review files -->
			{url|assign:limitReviewFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.LimitReviewFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}
			{load_url_in_div id="limitReviewFilesGrid" url=$limitReviewFilesGridUrl}
		</div>
	</div>

	{fbvFormSection list=true title="editor.submissionReview.reviewType"}
		{foreach from=$reviewMethods key=methodId item=methodTranslationKey}
			{assign var=elementId value="reviewMethod"|concat:"-"|concat:$methodId}
			{if $reviewMethod == $methodId}
				{assign var=elementChecked value=true}
			{else}
				{assign var=elementChecked value=false}
			{/if}
			{fbvElement type="radio" name="reviewMethod" id=$elementId value=$methodId checked=$elementChecked label=$methodTranslationKey}
		{/foreach}
	{/fbvFormSection}

	<!-- All of the hidden inputs -->
	<input type="hidden" name="selectionType" value="{$selectionType|escape}" />
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />
</div>
