{**
 * templates/user/identityForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * User profile form.
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#identityForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="identityForm" method="post" action="{url op="saveIdentity"}" enctype="multipart/form-data">
	{* Help Link *}
	{help file="user-profile" class="pkp_help_tab"}

	{csrf}

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="identityFormNotification"}

	{fbvFormArea id="userNameInfo"}
		{fbvFormSection title="user.username"}
			{$username|escape}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="userFormCompactLeft"}
		{fbvFormSection title="user.name"}
			{fbvElement type="text" label="user.givenName" multilingual="true" required="true" id="givenName" value=$givenName maxlength="255" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" label="user.familyName" multilingual="true" id="familyName" value=$familyName maxlength="255" inline=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormSection for="preferredPublicName" description="user.preferredPublicName.description"}
		{fbvElement type="text" label="user.preferredPublicName" multilingual="true" name="preferredPublicName" id="preferredPublicName" value=$preferredPublicName size=$fbvStyles.size.LARGE}
	{/fbvFormSection}

	{fbvFormButtons hideCancel=true submitText="common.save"}

	<p>
		{capture assign="privacyUrl"}{url router=$smarty.const.ROUTE_PAGE page="about" op="privacy"}{/capture}
		{translate key="user.privacyLink" privacyUrl=$privacyUrl}
	</p>

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
