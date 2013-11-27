
{**
 * templates/controllers/grid/settings/sponsor/form/sponsorForm.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Sponsors grid form
 *}
 <script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#editSponsorForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="editSponsorForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.sponsor.SponsorGridHandler" op="updateSponsor"}">
{include file="controllers/notification/inPlaceNotification.tpl" notificationId="sponsorFormNotification"}

{fbvFormArea id="mastheadInfo"}
{fbvFormSection}
	{fbvElement type="text" label="manager.setup.institution" id="institution" value=$institution maxlength="90" required=true}
{/fbvFormSection}
{fbvFormSection}
	{fbvElement type="text" label="common.url" id="url" value=$url maxlength="255" required=true}
{/fbvFormSection}
{/fbvFormArea}

{if $gridId}
	<input type="hidden" name="gridId" value="{$gridId|escape}" />
{/if}
{if $rowId}
	<input type="hidden" name="rowId" value="{$rowId|escape}" />
{/if}
{if $sponsorId}
	<input type="hidden" name="sponsorId" value="{$sponsorId|escape}" />
{/if}
{fbvFormButtons}
</form>
<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
