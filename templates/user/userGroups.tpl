{**
 * templates/user/userGroups.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * The user group (role) selection part of the registration and profile forms.
 *}

{fbvFormArea id="userGroups" title="user.roles" class=border}
	{if $currentContext && ($currentContext->getSetting('allowRegAuthor') || $currentContext->getSetting('allowRegReviewer'))}
		{translate|assign:"userGroupSectionLabel" key="user.register.registerAs" contextName=$currentContext->getLocalizedName()}
		{fbvFormSection label=$userGroupSectionLabel translate=false list=true}
			{include file="user/userGroupSelfRegistration.tpl" context=$currentContext authorUserGroups=$authorUserGroups reviewerUserGroups=$reviewerUserGroups}
		{/fbvFormSection}
	{/if}

	{if $showOtherContexts}
		{capture assign="otherContextContent"}
			{foreach from=$contexts item=context}
				{if !$currentContext || $context->getId() != $currentContext->getId()}
				{fbvFormSection label=$context->getLocalizedName() list=true translate=false}
					{include file="user/userGroupSelfRegistration.tpl" context=$context authorUserGroups=$authorUserGroups reviewerUserGroups=$reviewerUserGroups}
				{/fbvFormSection}
				{/if}
			{/foreach}
		{/capture}

		{if $currentContext}
			<div id="userGroupExtraFormFields" class="left full">
				{include file="controllers/extrasOnDemand.tpl"
					id="userGroupExtras"
					widgetWrapper="#userGroupExtraFormFields"
					moreDetailsText="user.profile.form.showOtherPresses"
					lessDetailsText="user.profile.form.hideOtherPresses"
					extraContent=$otherContextContent
				}
			</div>
		{else}
			{$otherContextContent}
		{/if}
	{/if}
{/fbvFormArea}
