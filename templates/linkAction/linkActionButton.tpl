{**
 * linkActionButton.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Template that renders a button for a link action.
 *
 * Parameter:
 *  action: The LinkAction we create a button for.
 *  buttonId: The id of the link.
 *  hoverTitle: Whether to show the title as hover text only.
 *}

<a href="#" id="{$buttonId|escape}" {strip}
	{if $action->getImage()}
		class="{$action->getImage()|escape}"
		{if $hoverTitle}title="{$action->getTitle()|escape}">&nbsp;{else}>{$action->getTitle()|escape}{/if}
	{else}
		{if $hoverTitle} title="{$action->getTitle()|escape}">{else}>{$action->getTitle()|escape}{/if}
	{/if}
{/strip}</a>
