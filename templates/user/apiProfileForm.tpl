{**
 * templates/user/apiProfileForm.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Public user profile form.
 *}

{* Help Link *}
{help file="user-profile.md" class="pkp_help_tab"}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#apiProfileForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="apiProfileForm" method="post" action="{url op="saveAPIProfile"}" enctype="multipart/form-data">
	{csrf}

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="apiProfileNotification"}

	{fbvFormSection list=true}
		{fbvElement id=apiKeyEnabled type="checkbox" label="user.apiKeyEnabled" checked=$apiKeyEnabled|compare:true:true:false value=1}
		{fbvElement id=generateApiKey type="checkbox" label="user.apiKey.generate" value=1}
	{/fbvFormSection}
	<p>{translate key="user.apiKey.generateWarning"}</p>

	{fbvFormSection}
		{if !$apiKey}{assign var=apiKey value="common.none"|translate}{/if}
		{fbvElement id=apiKey type="text" label="user.apiKey" readonly="true" value=$apiKey size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{fbvFormButtons hideCancel=true submitText="common.save"}
</form>
