{**
 * templates/frontend/components/registrationFormContexts.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Display role selection for all of the journals/presses on this site
 *
 * @uses $contexts array List of journals/presses on this site that have enabled registration
 * @uses $readerUserGroups array Associative array of user groups with reader
 *  permissions in each context.
 * @uses $authorUserGroups array Associative array of user groups with author
 *  permissions in each context.
 * @uses $reviewerUserGroups array Associative array of user groups with reviewer
 *  permissions in each context.
 * @uses $userGroupIds array List group IDs this user is assigned
 *}

{* Only display the context role selection when registration is taking place
   outside of the context of any one journal/press. *}
{if !$currentContext}

	{* Allow users to register for any journal/press on this site *}
	<fieldset name="contexts">
		<legend>
			{translate key="user.register.contextsPrompt"}
		</legend>
		<div class="fields">
			<div id="contextOptinGroup" class="context_optin">
				<ul class="contexts">
					{foreach from=$contexts item=context}
						{assign var=contextId value=$context->getId()}
						{assign var=isSelected value=false}
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
										{if in_array($userGroupId, $userGroupIds)}
											{assign var=isSelected value=true}
										{/if}
									{/if}
								{/foreach}
								{foreach from=$reviewerUserGroups[$contextId] item=userGroup}
									{if $userGroup->getPermitSelfRegistration()}
										{assign var="userGroupId" value=$userGroup->getId()}
										<label>
											<input type="checkbox" name="reviewerGroup[{$userGroupId}]"{if in_array($userGroupId, $userGroupIds)} checked="checked"{/if}>
											{$userGroup->getLocalizedName()}
										</label>
										{if in_array($userGroupId, $userGroupIds)}
											{assign var=isSelected value=true}
										{/if}
									{/if}
								{/foreach}
							</fieldset>
							{* Require the user to agree to the terms of the context's privacy policy *}
							{if !$enableSiteWidePrivacyStatement && $context->getSetting('privacyStatement')}
								<div class="context_privacy {if $isSelected}context_privacy_visible{/if}">
									<label>
										<input type="checkbox" name="privacyConsent[{$contextId}]" id="privacyConsent[{$contextId}]" value="1"{if $privacyConsent[$contextId]} checked="checked"{/if}>
										{capture assign="privacyUrl"}{url router=$smarty.const.ROUTE_PAGE context=$context->getPath() page="about" op="privacy"}{/capture}
										{translate key="user.register.form.privacyConsentThisContext" privacyUrl=$privacyUrl}
									</label>
								</div>
							{/if}
						</li>
					{/foreach}
				</ul>
			</div>
		</div>
	</fieldset>
{/if}
