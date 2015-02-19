{**
 * linkActionButton.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
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
<a href="javascript:$.noop();" id="{$buttonId|escape}"{strip}
	{/strip}{if $action->getImage()}{strip}
		{/strip} class="{$imageClass} {$action->getImage()|escape} pkp_controllers_linkAction"{strip}
		{/strip} title="{$action->getHoverTitle()|escape}">{if $hoverTitle}&nbsp;{else}{$action->getTitle()|escape}{/if}{strip}
	{/strip}{else}{strip}
		{/strip} class="pkp_controllers_linkAction"{strip}
		{/strip} title="{$action->getHoverTitle()|escape}">{if !$hoverTitle}{$action->getTitle()|strip_unsafe_html}{/if}{strip}
	{/strip}{/if}{strip}
{/strip}</a>
