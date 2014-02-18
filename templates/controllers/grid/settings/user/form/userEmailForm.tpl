{**
 * controllers/grid/settings/user/form/userEmailForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display form to send user an email.
 *}
 <script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#sendEmailForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="sendEmailForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.user.UserGridHandler" op="sendEmail"}" >

	<input type="hidden" name="userId" value="{$userId|escape}" />

	{fbvFormSection title="email.to"}
		{fbvElement type="text" id="user" value=$userFullName|concat:" <":$userEmail:">" disabled="true" size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{fbvFormSection title="email.subject" for="subject" required="true"}
		{fbvElement type="text" id="subject" value=$subject size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{fbvFormSection title="email.body" for="message" required="true"}
		{fbvElement type="textarea" id="message" value=$message size=$fbvStyles.size.LARGE}
	{/fbvFormSection}

	{fbvFormButtons submitText="common.sendEmail"}
</form>
<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
