{**
 * plugins/generic/webFeed/templates/atom.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Atom feed template
 *
 *}
<?xml version="1.0" encoding="{$defaultCharset|escape}"?>
<feed xmlns="http://www.w3.org/2005/Atom">
	{* required elements *}
	<id>{url page="feed" op="feed"}</id>
	<title>{$server->getLocalizedName()|escape:"html"|strip}</title>

	<updated>{$latestDate|date_format:"%Y-%m-%dT%T%z"|regex_replace:"/00$/":":00"}</updated>

	{* recommended elements *}
	{if $server->getData('contactName')}
		<author>
			<name>{$server->getData('contactName')|strip|escape:"html"}</name>
			{if $server->getData('contactEmail')}
			<email>{$server->getData('contactEmail')|strip|escape:"html"}</email>
			{/if}
		</author>
	{/if}

	<link rel="alternate" href="{url server=$server->getPath()}" />
	<link rel="self" type="application/atom+xml" href="{url page="feed" op="atom"}" />

	{* optional elements *}

	{* <category/> *}
	{* <contributor/> *}

	<generator uri="https://pkp.sfu.ca/ops/" version="{$systemVersion|escape}">Open Server Systems</generator>
	{if $server->getLocalizedDescription()}
		{assign var="description" value=$server->getLocalizedDescription()}
	{elseif $server->getLocalizedData('searchDescription')}
		{assign var="description" value=$server->getLocalizedData('searchDescription')}
	{/if}

	<subtitle type="html">{$description|strip|escape:"html"}</subtitle>

	{foreach from=$submissions item=item}
		{assign var=submission value=$item.submission}
		{assign var=publication value=$submission->getCurrentPublication()}
		<entry>
			{* required elements *}
			<id>{url page="preprint" op="view" path=$submission->getBestId()}</id>
			<title>{$publication->getLocalizedTitle()|strip|escape:"html"}</title>
			<updated>{$publication->getData('lastModified')|date_format:"%Y-%m-%dT%T%z"|regex_replace:"/00$/":":00"}</updated>

			{* recommended elements *}

			{foreach from=$publication->getData('authors') item=author}
				<author>
					<name>{$author->getFullName(false)|strip|escape:"html"}</name>
					{if $author->getEmail()}
						<email>{$author->getEmail()|strip|escape:"html"}</email>
					{/if}
				</author>
			{/foreach}{* authors *}

			<link rel="alternate" href="{url page="preprint" op="view" path=$submission->getBestId()}" />

			{if $publication->getLocalizedData('abstract') || $includeIdentifiers}
				<summary type="html" xml:base="{url page="preprint" op="view" path=$submission->getBestId()}">
					{if $includeIdentifiers}
						{foreach from=$item.identifiers item=identifier}
							{$identifier.label|strip|escape:"html"}: {', '|implode:$identifier.values|strip|escape:"html"}&lt;br /&gt;
						{/foreach}{* summary identifiers *}
						&lt;br /&gt;
					{/if}
					{$publication->getLocalizedData('abstract')|strip|escape:"html"}
				</summary>
			{/if}

			{* optional elements *}

			{foreach from=$item.identifiers item=identifier}
				{foreach from=$identifier.values item=value}
					<category term="{$value|strip|escape:"html"}" label="{$identifier.label|strip|escape:"html"}" scheme="https://pkp.sfu.ca/ops/category/{$identifier.type|strip|escape:"html"}"/>
				{/foreach}
			{/foreach}{* categories *}

			{* <contributor/> *}

			<published>{$publication->getData('datePublished')|date_format:"%Y-%m-%dT%T%z"|regex_replace:"/00$/":":00"}</published>

			{* <source/> *}
			<rights>{translate|escape key="submission.copyrightStatement" copyrightYear=$publication->getData('copyrightYear') copyrightHolder=$publication->getLocalizedData('copyrightHolder')}</rights>
		</entry>
	{/foreach}{* submissions *}
</feed>
