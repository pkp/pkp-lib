{**
 * message.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Generic message page.
 * Displays a simple message and (optionally) a return link.
 *
 *}
{strip}
{include file="frontend/components/header.tpl"}
{/strip}

{if $message}{translate|assign:"messageTranslated" key=$message}{/if}

<p>{$messageTranslated}</p>

{if $backLink}
<p><a href="{$backLink}">{translate key=$backLinkLabel}</a></p>
{/if}

{include file="common/frontend/footer.tpl"}
