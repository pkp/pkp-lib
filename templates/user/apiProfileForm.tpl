{**
 * templates/user/apiProfileForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
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

	{fbvFormSection list=true}
		{fbvElement id=apiKeyEnabled type="checkbox" label="user.apiKeyEnabled" checked=$apiKeyEnabled value=1}
		{fbvElement id=generateApiKey type="checkbox" label="user.apiKey.generate" value=1}
	{/fbvFormSection}
	<p>{translate key="user.apiKey.generateWarning"}</p>

	{fbvFormSection}
		{if !$apiKey}{assign var=apiKey value="common.none"|translate}{/if}
		{fbvElement id=apiKey type="text" label="user.apiKey" readonly="true" value=$apiKey size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{fbvFormButtons hideCancel=true submitText="common.save"}

	<p>
		{capture assign="privacyUrl"}{url router=$smarty.const.ROUTE_PAGE page="about" op="privacy"}{/capture}
		{translate key="user.privacyLink" privacyUrl=$privacyUrl}
	</p>
</form>
