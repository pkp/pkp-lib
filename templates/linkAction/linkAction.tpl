{**
 * linkAction.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Create a link action
 *
 * Parameters:
 *  action: A LinkAction object.
 *}

{* Generate the link action's button. *}
{assign var=buttonId value=$id|concat:"-":$action->getId():"-button-"|uniqid}
{include file="linkAction/linkActionButton.tpl" action=$action buttonId=$buttonId}

<script type="text/javascript">
	{* Attach the action handler to the button. *}
	$(function() {ldelim}
		$('#{$buttonId}').pkpHandler(
			'$.pkp.controllers.linkAction.LinkActionHandler',
			{ldelim}
				{assign var="actionRequest" value=$action->getActionRequest()}
				actionRequest: '{$actionRequest->getJSLinkActionRequest()}',
				actionRequestOptions: {ldelim}
					{foreach name=actionRequestOptions from=$actionRequest->getLocalizedOptions() key=optionName item=optionValue}
						{if $optionValue}{$optionName}: '{$optionValue|escape:javascript}'{if !$smarty.foreach.actionRequestOptions.last},{/if}{/if}
					{/foreach}
				{rdelim},
			{rdelim});
	{rdelim});
</script>
