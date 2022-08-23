{**
 * templates/user/apiProfileForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Public user profile form.
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#apiProfileForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="apiProfileForm" method="post" action="{url op="saveAPIProfile"}" enctype="multipart/form-data">
	{* Help Link *}
	{help file="user-profile" class="pkp_help_tab"}

	{csrf}

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="apiProfileNotification"}

	{fbvFormSection title="user.apiKey"}
		{if !$apiKey}{assign var=apiKey value="common.none"|translate}{/if}
		{fbvElement id=apiKey type="text" readonly="true" inline=true value=$apiKey size=$fbvStyles.size.MEDIUM}
		{if !$apiSecretMissing}
			{fbvElement id=apiKeyAction type="hidden" readonly="true" value=$apiKeyAction}
			<button
				type="submit"
				{if $apiKeyAction === \PKP\user\form\APIProfileForm::API_KEY_DELETE}
					onClick="return confirm({translate|json_encode|escape key='user.apiKey.remove.confirmation.message'})"
					class="pkpButton pkpButton--isWarnable"
				{else}
					class="pkp_button pkp_button_primary"
				{/if}
			>
				{translate key=$apiKeyActionTextKey}
			</button>
		{/if}
		<p>
			{translate key=($apiKeyAction === \PKP\user\form\APIProfileForm::API_KEY_NEW) ? "user.apiKey.generateWarning" : "user.apiKey.removeWarning"}
		</p>
	{/fbvFormSection}

	<p>
		{capture assign="privacyUrl"}
			{url router=\PKP\core\PKPApplication::ROUTE_PAGE page="about" op="privacy"}
		{/capture}
		{translate key="user.privacyLink" privacyUrl=$privacyUrl}
	</p>
</form>
