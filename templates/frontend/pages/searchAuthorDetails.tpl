{**
 * templates/frontend/pages/searchAuthorDetails.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Index of published submissions by author.
 *
 *}
{strip}
{assign var="pageTitle" value="search.authorDetails"}
{include file="frontend/components/header.tpl"}
{/strip}
<div id="authorDetails">
<h3>{$authorName|escape}{if $affiliation}, {$affiliation|escape}{/if}{if $country}, {$country|escape}{/if}</h3>
<ul>
{foreach from=$submissions item=preprint}
	{assign var=sectionId value=$preprint->getCurrentPublication()->getData('sectionId')}
	{assign var=serverId value=$preprint->getData('contextId')}
	{assign var=server value=$servers[$serverId]}
	{assign var=section value=$sections[$sectionId]}
	{if $section && $server}
	<li>
		<em>{$section->getLocalizedTitle()|escape}</em><br />
		{$preprint->getLocalizedTitle()|strip_unsafe_html}<br/>
		<a href="{url server=$server->getPath() page="preprint" op="view" path=$preprint->getBestId()}" class="file">{if $preprint->getCurrentPublication()->getData('abstract')}{translate key="preprint.abstract"}{else}{translate key="preprint.details"}{/if}</a>
		{if ($preprint->getCurrentPublication()->getData('accessStatus') == $smarty.const.PREPRINT_ACCESS_OPEN)}
		{foreach from=$preprint->getGalleys() item=galley name=galleyList}
			&nbsp;<a href="{url server=$server->getPath() page="preprint" op="view" path=$preprint->getBestId()|to_array:$galley->getBestGalleyId()}" class="file">{$galley->getGalleyLabel()|escape}</a>
		{/foreach}
		{/if}
	</li>
	{/if}
{/foreach}
</ul>
</div>
{include file="frontend/components/footer.tpl"}

