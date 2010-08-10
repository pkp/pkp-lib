{** if the actOnId has not been specified, assume the id plays the role *}
{if !$actOnId}
	{assign var=actOnId value=$id}
{/if}

{* If we have no button id set then let's build our own button. *}
{if !$buttonId}
	{assign var=buttonId value=$id|concat:"-":$action->getId():"-button"}
	{if $action->getImage()}
		<a href="{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_LINK}{$action->getUrl()}{/if}" id="{$buttonId}" class="{if $actionCss}{$actionCss} {/if}{$action->getImage()}" {if $hoverTitle}title="{$action->getLocalizedTitle()}">&nbsp;{else}>{$action->getLocalizedTitle()}{/if}</a>
	{else}
		<a href="{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_LINK}{$action->getUrl()}{/if}" id="{$buttonId}" {if $actionCss}class="{$actionCss}"{/if} {if $hoverTitle} title="{$action->getLocalizedTitle()}">{else}>{$action->getLocalizedTitle()}{/if}</a>
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
		$(function() {ldelim}
			ajaxAction(
				'{$action->getType()}',
				'#{$actOnId}',
				'#{$buttonId}',
				'{$action->getUrl()}'
			);
		{rdelim});
	</script>
{/if}
