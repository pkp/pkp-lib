{**
 * templates/information/information.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Information page.
 *
 *}
{strip}
	{if !$contentOnly}
		{include file="common/header.tpl"}
	{/if}
{/strip}

<div id="information">
	<p>{$content|nl2br}</p>
</div>

{strip}
	{if !$contentOnly}
		{include file="common/footer.tpl"}
	{/if}
{/strip}
