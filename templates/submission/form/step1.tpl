{**
 * templates/submission/form/step1.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Step 1 of author submission process.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#submitStep1Form').pkpHandler('$.pkp.pages.submission.SubmissionStep1FormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="submitStep1Form" method="post" action="{url op="saveStep" path=$submitStep}">
{csrf}
{if $submissionId}<input type="hidden" name="submissionId" value="{$submissionId|escape}"/>{/if}
	<input type="hidden" name="submissionChecklist" value="1"/>

{include file="controllers/notification/inPlaceNotification.tpl" notificationId="submitStep1FormNotification"}

{fbvFormArea id="submissionStep1"}

	{$additionalFormContent1}

	{include file="core:submission/submissionLocale.tpl"}

	{$additionalFormContent2}

	{* Submission checklist *}
	{if $currentContext->getLocalizedSetting('submissionChecklist')}
		{fbvFormSection list="true" label="submission.submit.submissionChecklist" description="submission.submit.submissionChecklistDescription" id="pkp_submissionChecklist"}
			{foreach name=checklist from=$currentContext->getLocalizedSetting('submissionChecklist') key=checklistId item=checklistItem}
				{fbvElement type="checkbox" id="checklist-$checklistId" required=true value=1 label=$checklistItem.content translate=false checked=false}
			{/foreach}
		{/fbvFormSection}
	{/if}

	{* Cover Note To Editor*}
	{fbvFormSection for="commentsToEditor" title="submission.submit.coverNote"}
		{fbvElement type="textarea" name="commentsToEditor" id="commentsToEditor" value=$commentsToEditor rich=true}
	{/fbvFormSection}

	{* Submitting in which role? *}
	{if $noExistingRoles}
		{if count($userGroupOptions) > 1}
			{fbvFormSection label="submission.submit.availableUserGroups" description="submission.submit.availableUserGroupsDescription" list=true required=true}
				{foreach from=$userGroupOptions key="userGroupId" item="userGroupName"}
					{if $defaultGroup->getId() == $userGroupId}{assign var="checked" value=true}{else}{assign var="checked" value=false}{/if}
					{fbvElement type="radio" id="userGroup"|concat:$userGroupId name="userGroupId" value=$userGroupId checked=$checked label=$userGroupName translate=false}
				{/foreach}
			{/fbvFormSection}
		{else}
			{foreach from=$userGroupOptions key="userGroupId" item="userGroupName"}
				{capture assign="onlyUserGroupId"}{$userGroupId}{/capture}
			{/foreach}
			{fbvFormSection label="submission.submit.contactConsent" list=true required=true}
				{fbvElement type="checkbox" id="userGroupId" required=true value=$onlyUserGroupId label="submission.submit.contactConsentDescription"}
			{/fbvFormSection}
		{/if}

	{* If user has existing roles, show available roles or automatically select single role *}
	{else}
		{if count($userGroupOptions) > 1}
			{fbvFormSection label="submission.submit.availableUserGroups" list=true required=true}
				{if $managerGroups}
					{translate key='submission.submit.userGroupDescriptionManagers' managerGroups=$managerGroups}
				{else}
					{translate key='submission.submit.userGroupDescription'}
				{/if}
				{foreach from=$userGroupOptions key="userGroupId" item="userGroupName"}
					{if $defaultGroup->getId() == $userGroupId}{assign var="checked" value=true}{else}{assign var="checked" value=false}{/if}
					{fbvElement type="radio" id="userGroup"|concat:$userGroupId name="userGroupId" value=$userGroupId checked=$checked label=$userGroupName translate=false}
				{/foreach}
			{/fbvFormSection}
		{elseif count($userGroupOptions) == 1}
			{foreach from=$userGroupOptions key="userGroupId" item="authorUserGroupName"}{assign var=userGroupId value=$userGroupId}{/foreach}
			{fbvElement type="hidden" id="userGroupId" value=$userGroupId}
		{/if}
	{/if}

	{if $copyrightNoticeAgree}
		{fbvFormSection title="submission.submit.copyrightNoticeAgreementLabel"}
			{$copyrightNotice}
			{fbvFormSection list="true"}
				{fbvElement type="checkbox" id="copyrightNoticeAgree" required=true value=1 label="submission.submit.copyrightNoticeAgree" checked=$submissionId}
			{/fbvFormSection}
		{/fbvFormSection}
	{/if}

	{* Privacy Statement *}
	{fbvFormSection list="true"}
		{capture assign="privacyUrl"}{url router=$smarty.const.ROUTE_PAGE page="about" op="privacy"}{/capture}
		{capture assign="privacyLabel"}{translate key="user.register.form.privacyConsent" privacyUrl=$privacyUrl}{/capture}
		{fbvElement type="checkbox" id="privacyConsent" required=true value=1 label=$privacyLabel translate=false checked=$privacyConsent}
	{/fbvFormSection}

	{* Buttons *}
	{fbvFormButtons id="step1Buttons" submitText="common.saveAndContinue"}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
{/fbvFormArea}

</form>
