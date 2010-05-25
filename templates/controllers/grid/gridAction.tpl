{** if the actOnId has not been specified, assume the id plays the role *}
{if !$actOnId}
	{assign var=actOnId value=$id}
{/if}

{assign var=buttonId value=$id|concat:"-":$action->getId():"-button"}

{if $action->getMode() eq $smarty.const.GRID_ACTION_MODE_MODAL}
	{modal url=$action->getUrl() actOnType=$action->getType() actOnId=$actOnId button="#"|concat:$buttonId}

{elseif $action->getMode() eq $smarty.const.GRID_ACTION_MODE_CONFIRM}
	{if $action->getLocalizedConfirmMessage()}
		{assign var="dialogText" value=$action->getLocalizedConfirmMessage()}
	{else}
		{assign var="dialogText" value=$action->getLocalizedTitle()}
	{/if}
	
	{confirm url=$action->getUrl() dialogText=$dialogText actOnType=$action->getType() actOnId=$actOnId button="#"|concat:$buttonId}

{elseif $action->getMode() eq $smarty.const.GRID_ACTION_MODE_AJAX}
	<script type='text/javascript'>
		ajaxAction(
			'{$action->getType()}',
			'{$actOnId}',
			'#{$buttonId}',
			'{$action->getUrl()}'
		);
	</script>

{/if}
{if $action->getImage()}
	<a href="{if $action->getMode() eq $smarty.const.GRID_ACTION_MODE_LINK}{$action->getUrl()}{/if}" id="{$buttonId}" class="{if $actionCss}{$actionCss} {/if}{$action->getImage()}">{$action->getLocalizedTitle()}</a>
{else}
	<a href="{if $action->getMode() eq $smarty.const.GRID_ACTION_MODE_LINK}{$action->getUrl()}{/if}" id="{$buttonId}" {if $actionCss}class="{$actionCss}"{/if}>{$action->getLocalizedTitle()}</a>
{/if}
