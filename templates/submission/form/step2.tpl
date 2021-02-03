{**
 * templates/submission/form/step2.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Step 2 of author submission.
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#submitStep2Form').pkpHandler('$.pkp.pages.submission.SubmissionStep2FormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="submitStep2Form" method="post" action="{url op="saveStep" path=$submitStep}" enctype="multipart/form-data" onsubmit="$.pkp.pages.submission.SubmissionStep2FormHandler.prototype.checkSubmit(event)">
	{csrf}
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="submitStep2FormNotification"}

	<div id="submission-files-container">
		<submission-files-list-panel v-bind="components.submissionFiles" @set="set"></submission-files-list-panel>
		<script type="text/javascript">
			pkp.registry.init('submission-files-container', 'Container', {$state|json_encode});
		</script>
	</div>

	{fbvFormButtons id="step2Buttons" submitText="common.saveAndContinue"}
</form>