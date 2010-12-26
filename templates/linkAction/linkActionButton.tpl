{**
 * linkActionButton.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Template that renders a button for a link action.
 *
 * Parameter:
 *  action: The LinkAction we create a button for.
 *  buttonId: The id of the link.
 *  hoverTitle: Whether to show the title as hover text only.
 *}

<a href="#" id="{$buttonId}" {strip}
	{if $action->getImage()}
		class="{$action->getImage()}"
		{if $hoverTitle}title="{translate key=$action->getTitle()}">&nbsp;{else}>{translate key=$action->getTitle()}{/if}
	{else}
		{if $hoverTitle} title="{translate key=$action->getTitle()}">{else}>{translate key=$action->getTitle()}{/if}
	{/if}
{/strip}</a>
