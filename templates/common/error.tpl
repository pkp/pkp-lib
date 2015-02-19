{**
 * error.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Generic error page.
 * Displays a simple error message and (optionally) a return link.
 *
 *}
{strip}
{include file="common/header.tpl"}
{/strip}

<span class="errorText">{translate key=$errorMsg params=$errorParams}</span>

{if $backLink}
<ul>
	<li><a id="backLink" href="{$backLink}">{translate key="$backLinkLabel"}</li>
</ul>
{/if}

{include file="common/footer.tpl"}
