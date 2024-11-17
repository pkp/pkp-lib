{**
 * templates/user/identityForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * User profile form.
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#identityForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

		$('#deleteOrcidButton').on('click', function(e) {
			const isModalConfirmTrigger = !e.originalEvent;
			// Only execute logic when button was clicked via ButtonConfirmationModalHandler
			if(isModalConfirmTrigger){
				$('#identityForm').append('<input type="checkbox" id="removeOrcidId" name="removeOrcidId"  checked value="true"/>');
				$('#identityForm').submit();
				$('#removeOrcidId').remove();
			}
		});
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

	{if $orcidEnabled}

	<div class="orcid_container">
		{* FIXME: The form element is still required for "connect ORCID" functionality to work. *}
		{fbvFormSection }
		{fbvElement type="text" label="user.orcid" name="orcid" id="orcid" value=$orcid maxlength="46"}

		{include file="form/orcidProfile.tpl"}
		{if $orcid && $orcidAuthenticated}
			{include file="linkAction/buttonConfirmationLinkAction.tpl" modalStyle="negative" buttonSelector="#deleteOrcidButton" dialogText="orcid.field.deleteOrcidModal.message"}
			<button id="deleteOrcidButton" type="button"  class="pkp_button pkp_button_offset" style="margin-left: 1rem">{translate key='common.delete'}</button>
		{/if}
		{/fbvFormSection}
	</div>
		<style>
			.orcid_container> .section {
				display:flex;
			}
		</style>
	{/if}

	<p>
		{capture assign="privacyUrl"}{url router=PKP\core\PKPApplication::ROUTE_PAGE page="about" op="privacy"}{/capture}
		{translate key="user.privacyLink" privacyUrl=$privacyUrl}
	</p>

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

	{fbvFormButtons hideCancel=true submitText="common.save"}
</form>
