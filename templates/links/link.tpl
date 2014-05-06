{**
 * templates/links/link.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a link category and associated links.
 *
 *}
{strip}
{assign var="pageTitleTranslated" value=$category->getLocalizedTitle()|strip_unsafe_html}
{include file="common/header.tpl"}
{/strip}

<div id="linkInfo">
	<p>
		{$category->getLocalizedDescription()|strip_unsafe_html}
	</p>
	<ul>
		{foreach from=$links item=link}
			<li><a href="{$link->getUrl()}">{$link->getLocalizedTitle()|strip_unsafe_html}</a></li>
		{/foreach}
	</ul>
</div>

{include file="common/footer.tpl"}
