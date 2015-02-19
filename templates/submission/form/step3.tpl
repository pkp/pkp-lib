{**
 * templates/submission/form/step3.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Step 3 of author submission.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the JS form handler.
		$('#submitStep3Form').pkpHandler(
			'$.pkp.pages.submission.SubmissionStep3FormHandler',
			{ldelim}
				chaptersGridContainer: 'chaptersGridContainer',
				authorsGridContainer: 'authorsGridContainer',
				canExpedite: {if $canExpedite}true{else}false{/if},
			{rdelim});
	{rdelim});
</script>

<form class="pkp_form" id="submitStep3Form" method="post" action="{url op="saveStep" path=$submitStep}">
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="submitStep3FormNotification"}

	{include file="core:submission/submissionMetadataFormTitleFields.tpl"}

	{fbvFormArea id="contributors"}
		<!--  Contributors -->
		{url|assign:authorGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.users.author.AuthorGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}
		{load_url_in_div id="authorsGridContainer" url=$authorGridUrl}

		{$additionalContributorsFields}
	{/fbvFormArea}

	{$additionalFormFields}

	{if $canExpedite}
		<div id="metadataAccordion"><h3><a href="#">{translate key="submission.submit.extendedMetadata"}</a></h3>
			<div id="extraSubmissionFields">
	{/if}
	{include file="core:submission/submissionMetadataFormFields.tpl"}
	{if $canExpedite}
			</div>
		</div>
	{/if}
	{fbvFormButtons id="step3Buttons" submitText="submission.submit.finishSubmission" confirmSubmit="submission.confirmSubmit"}
</form>
