{**
 * controllers/grid/settings/user/form/userForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form for creating/editing a user.
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#userForm').pkpHandler('$.pkp.controllers.grid.settings.user.form.UserFormHandler',
			{ldelim}
				fetchUsernameSuggestionUrl: '{url|escape:"javascript" router=$smarty.const.ROUTE_COMPONENT component="api.user.UserApiHandler" op="suggestUsername" firstName="FIRST_NAME_DUMMY" lastName="LAST_NAME_DUMMY" escape=false}',
				usernameSuggestionTextAlert: '{translate key="grid.user.mustProvideName"}'
			{rdelim}
		);
	{rdelim});
</script>

{if !$userId}
	{assign var="passwordRequired" value="true"}
{/if}{* !$userId *}

<form class="pkp_form" id="userForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.user.UserGridHandler" op="updateUser"}">
	<div id="userFormContainer">
		<div id="userDetails" class="full left">
			{if $userId}
				<h3>{translate key="grid.user.userDetails"}</h3>
			{else}
				<h3>{translate key="grid.user.step1"}</h3>
			{/if}
			{if $userId}
				<input type="hidden" id="userId" name="userId" value="{$userId|escape}" />
			{/if}
			{include file="controllers/notification/inPlaceNotification.tpl" notificationId="userFormNotification"}
		</div>

		{if $implicitAuth || $userId}{assign var="disableSendNotifySection" value=true}{/if}
		{include
			file="common/userDetails.tpl"
			disableAuthSourceSection=!$authSourceOptions
			disablePasswordSection=$implicitAuth
			disableSendNotifySection=$disableSendNotifySection
		}

		{if $userId}
			<div id="userRoles" class="full left">
				<div id="userRolesContainer" class="full left">
					{url|assign:userRolesUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.users.UserUserGroupListbuilderHandler" op="fetch" userId=$userId title="grid.user.userRoles" escape=false}
					{load_url_in_div id="userRolesContainer" url=$userRolesUrl}
				</div>
			</div>
		{/if}
		{fbvFormButtons}
	</div>
</form>
<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
