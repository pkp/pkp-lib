{**
 * templates/frontend/pages/userRegister.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User registration form.
 *}
{include file="frontend/components/header.tpl" pageTitle="user.register"}

<div class="page page_register">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="user.register"}

	<form class="pkp_form register" id="register" method="post" action="{url op="registerUser"}">

		{if $source}
			<input type="hidden" name="source" value="{$source|escape}" />
		{/if}

		{include file="common/formErrors.tpl"}

		<fieldset class="identity">
			<legend>
				{translate key="user.profile"}
			</legend>
			<div class="fields">
				<div class="first_name">
					<label>
						<span class="label">
							{translate key="user.firstName"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="text" name="firstName" id="firstName" value="{$firstName|escape}" maxlength="40" required>
					</label>
				</div>
				<div class="middle_name">
					<label>
						<span class="label">
							{translate key="user.middleName"}
						</span>
						<input type="text" name="middleName" value="{$middleName|escape}" maxlength="40">
					</label>
				</div>
				<div class="last_name">
					<label>
						<span class="label">
							{translate key="user.lastName"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="text" name="lastName" id="lastName" value="{$lastName|escape}" maxlength="40" required>
					</label>
				</div>
				<div class="affiliation">
					<label>
						<span class="label">
							{translate key="user.affiliation"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						{assign var="primaryLocale" value=$currentContext->getPrimaryLocale()}
						<input type="text" name="affiliation[{$primaryLocale|escape}]" id="affiliation" value="{$affiliation.$primaryLocale|escape}" required>
					</label>
				</div>
				<div class="country">
					<label>
						<span class="label">
							{translate key="common.country"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<select name="country" id="country" required>
							<option></option>
							{html_options options=$countries selected=$country}
						</select>
					</label>
				</div>
			</div>
		</fieldset>

		<fieldset class="login">
			<legend>
				{translate key="user.login"}
			</legend>
			<div class="fields">
				<div class="email">
					<label>
						<span class="label">
							{translate key="user.email"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="text" name="email" id="email" value="{$email|escape}" maxlength="90" required>
					</label>
				</div>
				<div class="username">
					<label>
						<span class="label">
							{translate key="user.username"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="text" name="username" id="username" value="{$username|escape}" maxlength="32" required>
					</label>
				</div>
				<div class="password">
					<label>
						<span class="label">
							{translate key="user.password"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="password" name="password" id="password" password="true" maxlength="32" required="$passwordRequired">
					</label>
				</div>
				<div class="password">
					<label>
						<span class="label">
							{translate key="user.repeatPassword"}
							<span class="required">*</span>
							<span class="pkp_screen_reader">
								{translate key="common.required"}
							</span>
						</span>
						<input type="password" name="password2" id="password2" password="true" maxlength="32" required="$passwordRequired">
					</label>
				</div>
			</div>
		</fieldset>

		{* Automatically opt-in all users to the Reader and Author roles in the
		   current journal/press *}
		{assign var=contextId value=$currentContext->getId()}
		{foreach from=$readerUserGroups[$contextId] item=userGroup}
			{if $userGroup->getPermitSelfRegistration()}
				{assign var="userGroupId" value=$userGroup->getId()}
				<input type="hidden" name="readerGroup[{$userGroupId}]" value="1">
			{/if}
		{/foreach}
		{foreach from=$authorUserGroups[$contextId] item=userGroup}
			{if $userGroup->getPermitSelfRegistration()}
				{assign var="userGroupId" value=$userGroup->getId()}
				<input type="hidden" name="authorGroup[{$userGroupId}]" value="1">
			{/if}
		{/foreach}

		{* Allow the user to sign up as a reviewer *}
		{assign var=userCanRegisterReviewer value=0}
		{foreach from=$reviewerUserGroups[$contextId] item=userGroup}
			{if $userGroup->getPermitSelfRegistration()}
				{assign var=userCanRegisterReviewer value=$userCanRegisterReviewer+1}
			{/if}
		{/foreach}
		{if $userCanRegisterReviewer}
			<fieldset class="reviewer">
				<legend>
					{translate key="user.reviewerPrompt"}
				</legend>
				<div class="fields">
					<div id="reviewerOptinGroup" class="optin">
						{foreach from=$reviewerUserGroups[$contextId] item=userGroup}
							{if $userGroup->getPermitSelfRegistration()}
								<label>
									{assign var="userGroupId" value=$userGroup->getId()}
									<input type="checkbox" name="reviewerGroup[{$userGroupId}]" value="1"{if in_array($userGroupId, $userGroupIds)} checked="checked"{/if}>
									{translate key="user.reviewerPrompt.userGroup" userGroup=$userGroup->getLocalizedName()}
								</label>
							{/if}
						{/foreach}
					</div>
					<div id="reviewerInterests" class="reviewer_interests">
						{*
						 * This container will be processed by the tag-it jQuery
						 * plugin. In order for it to work, your theme will need to
						 * load the jQuery tag-it plugin and initialize the
						 * component.
						 *
						 * Two data attributes are added which are not a default
						 * feature of the plugin. These are converted into options
						 * when the plugin is initialized on the element.
						 *
						 * See: /plugins/themes/default/js/main.js
						 *
						 * `data-field-name` represents the name used to POST the
						 * interests when the form is submitted.
						 *
						 * `data-autocomplete-url` is the URL used to request
						 * existing entries from the server.
						 *
						 * @link: http://aehlke.github.io/tag-it/
						 *}
						<div class="label">
							{translate key="user.interests"}
						</div>
						<ul class="interests tag-it" data-field-name="interests[]" data-autocomplete-url="{url|escape router=$smarty.const.ROUTE_PAGE page='user' op='getInterests'}">
							{foreach from=$interests item=interest}
								<li>{$interest|escape}</li>
							{/foreach}
						</ul>
					</div>
				</div>
			</fieldset>
		{/if}

		{* Allow users to register for other journals/presses on this site *}
		{if !$currentContext || $contexts|@count > 1}
			<fieldset name="contexts">
				<legend>
					{if !$currentContext}
						{translate key="user.register.contextsPrompt"}
					{else}
						{translate key="user.register.otherContextsPrompt"}
					{/if}
				</legend>
				<div class="fields">
					<div id="contextOptinGroup" class="context_optin">
						<ul class="contexts">
							{assign var=currentContextId value=$currentContext->getId()}
							{foreach from=$contexts item=context}
								{assign var=contextId value=$context->getId()}
								{if $contextId != $currentContextId}
									<li class="context">
										<div class="name">
											{$context->getLocalizedName()}
										</div class="name">
										<fieldset class="roles">
											<legend>
												{translate key="user.register.otherContextRoles"}
											</legend>
											{foreach from=$readerUserGroups[$contextId] item=userGroup}
												{if $userGroup->getPermitSelfRegistration()}
													{assign var="userGroupId" value=$userGroup->getId()}
													<label>
														<input type="checkbox" name="readerGroup[{$userGroupId}]"{if in_array($userGroupId, $userGroupIds)} checked="checked"{/if}>
														{$userGroup->getLocalizedName()}
													</label>
												{/if}
											{/foreach}
											{foreach from=$authorUserGroups[$contextId] item=userGroup}
												{if $userGroup->getPermitSelfRegistration()}
													{assign var="userGroupId" value=$userGroup->getId()}
													<label>
														<input type="checkbox" name="authorGroup[{$userGroupId}]"{if in_array($userGroupId, $userGroupIds)} checked="checked"{/if}>
														{$userGroup->getLocalizedName()}
													</label>
												{/if}
											{/foreach}
											{foreach from=$reviewerUserGroups[$contextId] item=userGroup}
												{if $userGroup->getPermitSelfRegistration()}
													{assign var="userGroupId" value=$userGroup->getId()}
													<label>
														<input type="checkbox" name="reviewerGroup[{$userGroupId}]"{if in_array($userGroupId, $userGroupIds)} checked="checked"{/if}>
														{$userGroup->getLocalizedName()}
													</label>
												{/if}
											{/foreach}
										</fieldset>
									</li>
								{/if}
							{/foreach}
						</ul>
					</div>
				</div>
			</fieldset>
		{/if}

		{if !$implicitAuth}
			<div class="required_label">
				{translate key="common.requiredField"}
			</div>
		{/if}

		<div class="buttons">
			<button class="submit" type="submit">
				{translate key="user.register"}
			</button>

			{if !$implicitAuth}
				{url|assign:"rolesProfileUrl" page="user" op="profile" path="roles"}
				<a href="{url page="login" source=$rolesProfileUrl}" class="login">{translate key="user.login"}</a>
			{/if}
		</div>
	</form>

</div><!-- .page -->

{include file="common/frontend/footer.tpl"}
