{**
 * controllers/grid/users/author/form/authorForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Submission Contributor grid form
 *
 *}

<script>
	// Attach the Information Center handler.
	$(function() {ldelim}
		$('#editAuthor').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler'
		);
	{rdelim});
</script>

<form class="pkp_form" id="editAuthor" method="post" action="{url op="updateAuthor" authorId=$authorId}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="authorFormNotification"}

	{fbvFormArea id="profile"}
		{fbvFormSection title="user.name"}
			{fbvElement type="text" label="user.firstName" id="firstName" value=$firstName maxlength="40" required=true inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" label="user.middleName" id="middleName" value=$middleName maxlength="40" inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" label="user.lastName" id="lastName" value=$lastName maxlength="40" required=true inline=true size=$fbvStyles.size.SMALL}
			{fbvElement type="text" label="user.suffix" id="suffix" value=$suffix maxlength="40" inline=true size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
		<div class="pkp_helpers_clear"></div>
		{fbvFormSection title="about.contact"}
			{fbvElement type="text" label="user.email" id="email" value=$email maxlength="90" required=true inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" label="user.url" id="url" value=$url maxlength="255" inline=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection title="common.other"}
			{fbvElement type="text" label="user.affiliation" id="affiliation" inline=true value=$affiliation maxlength="40" size=$fbvStyles.size.MEDIUM}
			{* Country is required for ONIX export of author metadata *}
			{fbvElement type="select" label="common.country" id="country" inline=true from=$countries selected=$country translate=false size=$fbvStyles.size.MEDIUM defaultValue="" defaultLabel="" required=true}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="textArea" label="user.biography" id="biography" value=$biography size=$fbvStyles.size.Large rich=true}
		{/fbvFormSection}
	{/fbvFormArea}
	{fbvFormArea id="submissionSpecific"}
		{fbvFormSection id="userGroupId" label="submission.submit.contributorRole" list=true}
			{iterate from=authorUserGroups item=userGroup}
				{if $userGroupId == $userGroup->getId()}{assign var="checked" value=true}{else}{assign var="checked" value=false}{/if}
				{fbvElement type="radio" id="userGroup"|concat:$userGroup->getId() name="userGroupId" value=$userGroup->getId() checked=$checked label=$userGroup->getLocalizedName() translate=false}
			{/iterate}
		{/fbvFormSection}
		{fbvFormSection list="true"}
			{fbvElement type="checkbox" label="submission.submit.selectPrincipalContact" id="primaryContact" checked=$primaryContact}
		{/fbvFormSection}
	{/fbvFormArea}

	{if $submissionId}
		<input type="hidden" name="{$submissionIdFieldName|escape}" value="{$submissionId|escape}" />
	{/if}
	{if $gridId}
		<input type="hidden" name="gridId" value="{$gridId|escape}" />
	{/if}
	{if $rowId}
		<input type="hidden" name="rowId" value="{$rowId|escape}" />
	{/if}

	{fbvFormButtons id="step2Buttons" submitText="common.save"}
</form>
<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
