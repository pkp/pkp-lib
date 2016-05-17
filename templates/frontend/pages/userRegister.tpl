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

	<p>
		{translate key="user.register.completeForm"}
	</p>

	{if !$implicitAuth}
		{url|assign:"rolesProfileUrl" page="user" op="profile" path="roles"}
		{url|assign:"loginUrl" page="login" source=$rolesProfileUrl}
		<p>
			{translate key="user.register.alreadyRegisteredOtherContext" registerUrl=$loginUrl}
		</p>
	{/if}

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

		{* @todo Implement this without recourse to the Form Builder Vocab,
		    so we don't force themers to dip into FBV at all *}
		{include file="user/userGroups.tpl"}

		<div class="buttons">
			<button class="submit" type="submit">
				{translate key="user.register"}
			</button>
		</div>

		{if !$implicitAuth}
			<div class="required_label">
				{translate key="common.requiredField"}
			</div>
		{/if}
	</form>

</div><!-- .page -->

{include file="common/frontend/footer.tpl"}
