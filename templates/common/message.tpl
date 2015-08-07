{**
 * message.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Generic message page.
 * Displays a simple message and (optionally) a return link.
 *
 *}
{strip}
{include file="common/frontend/header.tpl"}
{/strip}

{if $message}{translate|assign:"messageTranslated" key=$message}{/if}

<p>{$messageTranslated}</p>

{if $backLink}
<p><a href="{$backLink}">{translate key=$backLinkLabel}</a></p>
{/if}

{include file="common/frontend/footer.tpl"}
