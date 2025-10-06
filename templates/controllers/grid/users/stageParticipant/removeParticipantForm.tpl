{**
 * templates/controllers/grid/users/stageParticipant/removeParticipantForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form to optionally notify a user when removing them as a stage participant.
 *}

<script type="text/javascript">
	$(function() {ldelim}
		$('#removeParticipantForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="removeParticipantForm" method="post" action="{url op="removeStageAssignment"}" >
	{csrf}
	<input type="hidden" name="assignmentId" value="{$assignmentId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />

	{fbvFormSection title="stageParticipants.notify.message" for="personalMessage"}
		{fbvElement type="textarea" name="personalMessage" id="personalMessage" value=$personalMessage rich=true}
	{/fbvFormSection}

	{fbvFormSection for="skipEmail" size=$fbvStyles.size.MEDIUM list=true}
		{fbvElement type="checkbox" id="skipEmail" name="skipEmail" label="email.skip"}
	{/fbvFormSection}

	{fbvFormButtons submitText="grid.action.remove"}
</form>