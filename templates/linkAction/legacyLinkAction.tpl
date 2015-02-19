{**
 * templates/linkAction/legacyLinkAction.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Deprecated template to render link actions.
 *}

{** if the actOnId has not been specified, assume the id plays the role *}
{if !$actOnId}
	{assign var=actOnId value=$id}
{/if}

{* If we have no button id set then let's build our own button. *}
{if !$buttonId}
	{assign var=buttonId value=$id|concat:"-":$action->getId():"-button"|uniqid}
	{if $action->getImage()}
		<a href="{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_LINK}{$action->getUrl()}{/if}" id="{$buttonId|escape}" class="{if $actionCss}{$actionCss|escape} {/if}{if $action->getImage()}sprite {$action->getImage()|escape}{/if}" {if $hoverTitle}title="{$action->getLocalizedTitle()|escape}">&nbsp;{else}>{$action->getLocalizedTitle()|escape}{/if}</a>
	{else}
		<a href="{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_LINK}{$action->getUrl()}{/if}" id="{$buttonId|escape}" {if $actionCss}class="{$actionCss|escape}"{/if} {if $hoverTitle} title="{$action->getLocalizedTitle()}">{else}>{$action->getLocalizedTitle()|escape}{/if}</a>
	{/if}
{/if}

{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_MODAL}
	{modal url=$action->getUrl() actOnType=$action->getType() actOnId="#"|concat:$actOnId button="#"|concat:$buttonId}

{elseif $action->getMode() eq $smarty.const.LINK_ACTION_MODE_CONFIRM}
	{if $action->getLocalizedConfirmMessage()}
		{assign var="dialogText" value=$action->getLocalizedConfirmMessage()}
	{else}
		{assign var="dialogText" value=$action->getLocalizedTitle()}
	{/if}

	{confirm url=$action->getUrl() dialogText=$dialogText actOnType=$action->getType() actOnId="#"|concat:$actOnId button="#"|concat:$buttonId  translate=false}

{elseif $action->getMode() eq $smarty.const.LINK_ACTION_MODE_AJAX}
	<script type="text/javascript">
		<!--
		$(function() {ldelim}
			ajaxAction(
				'{$action->getType()|escape:"javascript"}',
				'#{$actOnId|escape:"javascript"}',
				'#{$buttonId|escape:"javascript"}',
				'{$action->getUrl()|escape:"javascript"}'
			);
		{rdelim});
		// -->
	</script>
{/if}

