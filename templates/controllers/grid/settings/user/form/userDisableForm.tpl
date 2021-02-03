{**
 * templates/controllers/grid/settings/user/form/userDisableForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display form to enable/disable a user.
 *}
 <script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#userDisableForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="userDisableForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.user.UserGridHandler" op="disableUser"}">
	{csrf}

	<input type="hidden" name="userId" value="{$userId|escape}" />
	<input type="hidden" name="enable" value="{$enable|escape}" />

	{if $enable}
		{fbvFormSection title="grid.user.enableReason" for="disableReason"}
			{fbvElement type="textarea" id="disableReason" value=$disableReason size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
	{else}
		{fbvFormSection title="grid.user.disableReason" for="disableReason"}
			{fbvElement type="textarea" id="disableReason" value=$disableReason size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
	{/if}

	{fbvFormButtons}
</form>
