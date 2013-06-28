{**
 * controllers/grid/settings/user/form/userForm.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form for creating/editing a user.
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#userForm').pkpHandler('$.pkp.controllers.grid.settings.user.form.UserFormHandler',
			{ldelim}
				fetchUsernameSuggestionUrl: '{url|escape:"javascript" router=$smarty.const.ROUTE_COMPONENT component="grid.settings.user.UserGridHandler" op="suggestUsername" params=$suggestUsernameParams escape=false}',
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
		<div id="userFormCompactLeftContainer" class="pkp_helpers_clear">
			{fbvFormArea id="userFormCompactLeft"}
				{if !$userId}{capture assign="usernameInstruction"}{translate key="user.register.usernameRestriction"}{/capture}{/if}
				{fbvFormSection for="username" description=$usernameInstruction translate=false}
					{if !$userId}
						{fbvElement type="text" label="user.username" id="username" required="true" value=$username|escape maxlength="32" inline=true size=$fbvStyles.size.MEDIUM}
						{fbvElement type="button" label="common.suggest" id="suggestUsernameButton" inline=true class="default"}
					{else}
						{fbvFormSection title="user.username" suppressId="true"}
							{$username|escape}
						{/fbvFormSection}
					{/if}
				{/fbvFormSection}

				{fbvFormSection title="user.name"}
					{fbvElement type="text" label="user.firstName" required="true" id="firstName" value=$firstName|escape maxlength="40" inline=true size=$fbvStyles.size.SMALL}
					{fbvElement type="text" label="user.middleName" id="middleName" value=$middleName|escape maxlength="40" inline=true size=$fbvStyles.size.SMALL}
					{fbvElement type="text" label="user.lastName" required="true" id="lastName" value=$lastName|escape maxlength="40" inline=true size=$fbvStyles.size.SMALL}
				{/fbvFormSection}

				{fbvFormSection title="about.contact"}
					{fbvElement type="text" label="user.email" id="email" required="true" value=$email|escape maxlength="90" size=$fbvStyles.size.MEDIUM}
				{/fbvFormSection}

				{if $authSourceOptions}
					{fbvFormSection title="grid.user.authSource" for="authId"}
						{fbvElement type="select" name="authId" id="authId" defaultLabel="" defaultValue="" from=$authSourceOptions translate="true" selected=$authId}
					{/fbvFormSection}
				{/if}

				{if !$implicitAuth}
					{if $userId}{capture assign="passwordInstruction"}{translate key="user.profile.leavePasswordBlank"} {translate key="user.register.passwordLengthRestriction" length=$minPasswordLength}{/capture}{/if}
					{fbvFormArea id="passwordSection" class="border" title="user.password"}
						{fbvFormSection for="password" class="border" description=$passwordInstruction translate=false}
							{fbvElement type="text" label="user.password" required=$passwordRequired name="password" id="password" password="true" value=$password maxlength="32" inline=true size=$fbvStyles.size.MEDIUM}
							{fbvElement type="text" label="user.repeatPassword" required=$passwordRequired name="password2" id="password2" password="true" value=$password2 maxlength="32" inline=true size=$fbvStyles.size.MEDIUM}
						{/fbvFormSection}
						{if !$userId}
							{fbvFormSection title="grid.user.generatePassword" for="generatePassword" list=true}
								{if $generatePassword}
									{assign var="checked" value=true}
								{else}
									{assign var="checked" value=false}
								{/if}
								{fbvElement type="checkbox" name="generatePassword" id="generatePassword" checked=$checked label="grid.user.generatePasswordDescription" translate="true"}
							{/fbvFormSection}
						{/if}{* !$userId *}
						{fbvFormSection title="grid.user.mustChangePassword" for="mustChangePassword" list=true}
							{if $mustChangePassword}
								{assign var="checked" value=true}
							{else}
								{assign var="checked" value=false}
							{/if}
							{fbvElement type="checkbox" name="mustChangePassword" id="mustChangePassword" checked=$checked label="grid.user.mustChangePasswordDescription" translate="true"}
						{/fbvFormSection}
					{/fbvFormArea}

				{/if}{* !$implicitAuth *}

				{if !$implicitAuth && !$userId}
					{fbvFormSection title="grid.user.notifyUser" for="sendNotify" list=true}
						{if $sendNotify}
							{assign var="checked" value=true}
						{else}
							{assign var="checked" value=false}
						{/if}
						{fbvElement type="checkbox" name="sendNotify" id="sendNotify" checked=$checked label="grid.user.notifyUserDescription" translate="true"}
					{/fbvFormSection}
				{/if} {* !$implicitAuth && !$userId *}
			{/fbvFormArea}
		</div>
		{capture assign="extraContent"}
			<div id="userFormExtendedContainer" class="full left">
				{fbvFormArea id="userFormExtendedLeft"}
					{fbvFormSection}
						{fbvElement type="text" label="user.salutation" name="salutation" id="salutation" value=$salutation maxlength="40" inline=true size=$fbvStyles.size.SMALL}
						{fbvElement type="select" label="user.gender" name="gender" id="gender" defaultLabel="" defaultValue="" from=$genderOptions translate="true" selected=$gender inline=true size=$fbvStyles.size.SMALL}
						{fbvElement type="text" label="user.initials" name="initials" id="initials" value=$initials maxlength="5" inline=true size=$fbvStyles.size.SMALL}
					{/fbvFormSection}

					{fbvFormSection}
						{fbvElement type="text" label="user.url" name="userUrl" id="userUrl" value=$userUrl maxlength="255" inline=true size=$fbvStyles.size.SMALL}
						{fbvElement type="text" label="user.phone" name="phone" id="phone" value=$phone maxlength="24" inline=true size=$fbvStyles.size.SMALL}
						{fbvElement type="text" label="user.fax" name="fax" id="fax" value=$fax maxlength="24" inline=true size=$fbvStyles.size.SMALL}
					{/fbvFormSection}

					{if count($availableLocales) > 1}
						{fbvFormSection title="user.workingLanguages" list=true}
							{foreach from=$availableLocales key=localeKey item=localeName}
								{if $userLocales && in_array($localeKey, $userLocales)}
									{assign var="checked" value="true"}
								{else}
									{assign var="checked" value="false"}
								{/if}
								{fbvElement type="checkbox" name="userLocales[]" id="userLocales-$localeKey" value="$localeKey" checked=$checked label="$localeName" translate=false }
							{/foreach}
						{/fbvFormSection}
					{/if}
					{fbvFormSection for="interests"}
						{fbvElement type="interests" id="interests" interestsKeywords=$interestsKeywords interestsTextOnly=$interestsTextOnly label="user.interests"}
					{/fbvFormSection}

					{fbvFormSection for="affiliation"}
						{fbvElement type="text" label="user.affiliation" multilingual="true" name="affiliation" id="affiliation" value=$affiliation inline=true size=$fbvStyles.size.LARGE}
					{/fbvFormSection}

					{fbvFormSection}
						{fbvElement type="textarea" label="user.biography" multilingual="true" name="biography" id="biography" value=$biography inline=true size=$fbvStyles.size.MEDIUM}
						{fbvElement type="textarea" label="common.mailingAddress" name="mailingAddress" id="mailingAddress" value=$mailingAddress inline=true size=$fbvStyles.size.MEDIUM}
					{/fbvFormSection}
					<br />
					<span class="instruct">{translate key="user.biography.description"}</span>

					{fbvFormSection}
						{fbvElement type="textarea" label="user.signature" multilingual="true" name="signature" id="signature" value=$signature size=$fbvStyles.size.MEDIUM}
					{/fbvFormSection}

					{fbvFormSection for="country"}
						{fbvElement type="select" label="common.country" name="country" id="country" defaultLabel="" defaultValue="" from=$countries selected=$country translate="0" size=$fbvStyles.size.MEDIUM}
					{/fbvFormSection}
				{/fbvFormArea}
			</div>
		{/capture}
			<div id="userExtraFormFields" class="left full">
				{include file="controllers/extrasOnDemand.tpl"
					id="userExtras"
					widgetWrapper="#userExtraFormFields"
					moreDetailsText="grid.user.moreDetails"
					lessDetailsText="grid.user.lessDetails"
					extraContent=$extraContent
				}
			</div>
			{if $userId}
				<div id="userRoles" class="full left">
					<h3>{translate key="grid.user.userRoles"}</h3>
					<div id="userRolesContainer" class="full left">
						{url|assign:userRolesUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.users.UserUserGroupListbuilderHandler" op="fetch" userId=$userId title="grid.user.addRoles" escape=false}
						{load_url_in_div id="userRolesContainer" url=$userRolesUrl}
					</div>
				</div>
			{/if}
			{fbvFormButtons}
		</div>
</form>
