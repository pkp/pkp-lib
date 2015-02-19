{**
 * linkActionButton.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Template that renders a button for a link action.
 *
 * Parameter:
 *  action: The LinkAction we create a button for.
 *  buttonId: The id of the link.
 *  hoverTitle: Whether to show the title as hover text only.
 *}

{if !$imageClass}
	{assign var="imageClass" value="sprite"}
{/if}
<a href="javascript:$.noop();" id="{$buttonId|escape}" {strip}
	{if $action->getImage()}
		class="{$imageClass} {$action->getImage()|escape} pkp_controllers_linkAction"
		title="{$action->getHoverTitle()|escape}">{if $hoverTitle}&nbsp;{else}{$action->getTitle()|escape}{/if}
	{else}
		class="pkp_controllers_linkAction"
		title="{$action->getHoverTitle()|escape}">{if !$hoverTitle}{$action->getTitle()|escape}{/if}
	{/if}
{/strip}</a>
