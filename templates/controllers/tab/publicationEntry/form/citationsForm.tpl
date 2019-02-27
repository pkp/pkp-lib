{**
 * templates/controllers/tab/publicationEntry/form/citationsForm.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#citationsForm').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler',
			{ldelim}
				trackFormChanges: true
			{rdelim}
		);
	{rdelim});
</script>
<form class="pkp_form" id="citationsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="updateCitations"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="publicationCitationsFormFieldsNotification"}
	<input type="hidden" name="submissionId" value="{$submission->getId()|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="tabPos" value="{$tabPos|escape}" />
	<input type="hidden" name="displayedInContainer" value="{$formParams.displayedInContainer|escape}" />
	<input type="hidden" name="tab" value="citations" />
	{csrf}

	{fbvFormSection label="submission.citations" description="submission.citations.description"}
		{fbvElement type="textarea" id="citations" value=$citations disabled=$readOnly required=$citationsRequired}
	{/fbvFormSection}

	{$additionalNotifications}

	{if $parsedCitations->getCount()}
		{fbvFormSection label="submission.parsedCitations" description="submission.parsedCitations.description"}
			{iterate from=parsedCitations item=parsedCitation}
				<p>
					{$parsedCitation->getCitationWithLinks()|strip_unsafe_html} {call_hook name="Templates::Controllers::Tab::PublicationEntry::Form::CitationsForm::Citation" citation=$parsedCitation}
				</p>	
			{/iterate}
		{/fbvFormSection}
	{/if}
	
	<div class="section formButtons form_buttons ">
	{foreach from=$actionNames key=action item=actionName}
		{fbvElement type="submit" label="$actionName" id="$action" name="$action" value="1" class="$action" translate=false inline=true}
	{/foreach}
	<div>
</form>
