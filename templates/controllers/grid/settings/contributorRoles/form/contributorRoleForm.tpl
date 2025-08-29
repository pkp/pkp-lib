{**
 * templates/controllers/grid/settings/contributorRoles/form/contributorRoleForm.tpl
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Contributor role form under context management.
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#contributorRoleForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="contributorRoleForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.settings.contributorRoles.ContributorRoleGridHandler" op="updateContributorRole"}">
{csrf}
{include file="controllers/notification/inPlaceNotification.tpl" notificationId="contributorRoleFormNotification"}

{fbvFormArea id="contributorRolesDetails"}
{fbvFormSection title="manager.setup.contributorRoles.identifier" for="identifier" required="true"}
	{if !$identifier}
		{fbvElement type="select" id="identifier" required="true" from=$contributorRoles translate="0"}
	{else}
		{fbvElement type="text" id="identifier-disabled" value=$identifier disabled="true"}
	{/if}
{/fbvFormSection}
{fbvFormSection title="manager.setup.contributorRoles.name" for="name" required="true"}
	{fbvElement type="text" multilingual="true" id="name" value=$name maxlength="80" required="true"}
{/fbvFormSection}
{/fbvFormArea}

{if $gridId}
	<input type="hidden" name="gridId" value="{$gridId|escape}" />
{/if}
{if $rowId}
	<input type="hidden" name="rowId" value="{$rowId|escape}" />
{/if}
<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
{fbvFormButtons submitText="common.save"}
</form>
