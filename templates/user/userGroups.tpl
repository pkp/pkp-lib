{**
 * templates/user/userGroups.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * The user group (role) selection part of the registration and profile forms.
 *}

{fbvFormArea id="userGroups" title="user.roles" class=border}
	{if $currentContext}
		{translate|assign:"userGroupSectionLabel" key="user.register.registerAs" contextName=$currentContext->getLocalizedName()}
		{fbvFormSection label=$userGroupSectionLabel translate=false list=true}
			{include file="user/userGroupSelfRegistration.tpl" context=$currentContext authorUserGroups=$authorUserGroups reviewerUserGroups=$reviewerUserGroups readerUserGroups=$readerUserGroups}
		{/fbvFormSection}
	{/if}

	{if $showOtherContexts}
		{capture assign="otherContextContent"}
			{foreach from=$contexts item=context}
				{if !$currentContext || $context->getId() != $currentContext->getId()}
				{fbvFormSection title=$context->getLocalizedName() list=true translate=false}
					{include file="user/userGroupSelfRegistration.tpl" context=$context authorUserGroups=$authorUserGroups reviewerUserGroups=$reviewerUserGroups}
				{/fbvFormSection}
				{/if}
			{/foreach}
		{/capture}

		{if $currentContext}
			<div id="userGroupExtraFormFields" class="pkp_user_group_other_contexts">
				{include file="controllers/extrasOnDemand.tpl"
					id="userGroupExtras"
					widgetWrapper="#userGroupExtraFormFields"
					moreDetailsText="user.profile.form.showOtherContexts"
					lessDetailsText="user.profile.form.hideOtherContexts"
					extraContent=$otherContextContent
				}
			</div>
		{else}
			{$otherContextContent}
		{/if}
	{/if}

	{fbvFormSection for="interests"}
		{fbvElement type="interests" id="interests" interests=$interests label="user.interests"}
	{/fbvFormSection}
{/fbvFormArea}
