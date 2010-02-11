{** if the actOnId has not been specified, assume the id plays the role *}
{if !$actOnId}
	{assign var=actOnId value=$id}
{/if}

{assign var=buttonId value="`$id`-`$action->getId()`-button"}

{if $action->getMode() eq $smarty.const.GRID_ACTION_MODE_MODAL}
	{modal url=$action->getUrl() actOnType=$action->getType() actOnId=$actOnId button="#`$buttonId`"}

{elseif $action->getMode() eq $smarty.const.GRID_ACTION_MODE_CONFIRM}
	{confirm url=$action->getUrl() dialogText=$action->getTitle() actOnType=$action->getType() actOnId=$actOnId button="#`$buttonId`"}

{elseif $action->getMode() eq $smarty.const.GRID_ACTION_MODE_AJAX}
	<script type='text/javascript'>
		$(document).ready(function() {ldelim}
			$('#{$buttonId}').bind('click', function() {ldelim}
				$('#{$actOnId}').load('{$action->getUrl()}');
			{rdelim});
		{rdelim});
	</script>

{/if}
{if $action->getImage()}
	<a  href="" id="{$buttonId}" class="{$action->getImage()}">{translate key=$action->getTitle()}</a>
{else}
	<button type="button" id="{$buttonId}">{translate key=$action->getTitle()}</button>
{/if}