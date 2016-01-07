{**
 * templates/frontend/pages/error.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Generic error page.
 * Displays a simple error message and (optionally) a return link.
 *}
{strip}
{include file="frontend/components/header.tpl"}
{/strip}

<span class="errorText">{translate key=$errorMsg params=$errorParams}</span>

{if $backLink}
<br /><br />
&#187; <a id="backLink" href="{$backLink}">{translate key=$backLinkLabel}</a>
{/if}

{include file="common/frontend/footer.tpl"}
