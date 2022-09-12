{**
 * plugins/generic/webFeed/templates/rss2.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * RSS 2 feed template
 *
 *}
<?xml version="1.0" encoding="{$defaultCharset|escape}"?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://web.resource.org/cc/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<atom:link href="{$feedUrl}" rel="self" type="application/rss+xml" />
		{* required elements *}
		<title>{$server->getLocalizedName()|strip|escape:"html"}</title>
		<link>{url server=$server->getPath()}</link>

		{if $server->getLocalizedDescription()}
			{assign var="description" value=$server->getLocalizedDescription()}
		{elseif $server->getLocalizedData('searchDescription')}
			{assign var="description" value=$server->getLocalizedData('searchDescription')}
		{/if}

		<description>{$description|strip|escape:"html"}</description>

		{* optional elements *}
		{if $server->getPrimaryLocale()}
			<language>{$server->getPrimaryLocale()|replace:'_':'-'|strip|escape:"html"}</language>
		{/if}

		{if $server->getLocalizedData('licenseTerms')}
			<copyright>{$server->getLocalizedData('licenseTerms')|strip|escape:"html"}</copyright>
		{/if}

		{if $server->getData('contactEmail')}
			<managingEditor>{$server->getData('contactEmail')|strip|escape:"html"}{if $server->getData('contactName')} ({$server->getData('contactName')|strip|escape:"html"}){/if}</managingEditor>
		{/if}

		{if $server->getData('supportEmail')}
			<webMaster>{$server->getData('supportEmail')|strip|escape:"html"}{if $server->getData('contactName')} ({$server->getData('supportName')|strip|escape:"html"}){/if}</webMaster>
		{/if}

		<pubDate>{$latestDate|date_format:$smarty.const.DATE_RSS}</pubDate>

		{* <lastBuildDate/> *}
		{* <category/> *}
		{* <creativeCommons:license/> *}

		<generator>OPS {$systemVersion|escape}</generator>
		<docs>http://blogs.law.harvard.edu/tech/rss</docs>
		<ttl>60</ttl>

		{foreach from=$submissions item=item}
			{assign var=submission value=$item.submission}
			{assign var=publication value=$submission->getCurrentPublication()}
			<item>
				{* required elements *}
				<title>{$submission->getLocalizedTitle()|strip|escape:"html"}</title>
				<link>{url page="article" op="view" path=$submission->getBestId()}</link>
				<description>{$submission->getLocalizedAbstract()|strip|escape:"html"}</description>

				{* optional elements *}
				{* <author/> *}
				<dc:creator>{$submission->getAuthorString(false)|escape:"html"}</dc:creator>

				{foreach from=$item.identifiers item=identifier}
					<category domain="https://pkp.sfu.ca/ops/category/{$identifier.type|strip|escape:"html"}">{$identifier.value|strip|escape:"html"}</category>
				{/foreach}{* categories *}
				{* <comments/> *}
				{* <source/> *}

				<dc:rights>
					{translate|escape key="submission.copyrightStatement" copyrightYear=$submission->getCopyrightYear() copyrightHolder=$submission->getLocalizedCopyrightHolder()}
					{$submission->getLicenseURL()|escape}
				</dc:rights>
				{if $publication->getData('accessStatus') == \APP\submission\Submission::PREPRINT_ACCESS_OPEN && $submission->isCCLicense()}
					<cc:license rdf:resource="{$submission->getLicenseURL()|escape}" />
				{else}
					<cc:license></cc:license>
				{/if}

				<guid isPermaLink="true">{url page="article" op="view" path=$submission->getBestId()}</guid>
				{if $submission->getDatePublished()}
					{capture assign="datePublished"}{$submission->getDatePublished()|strtotime}{/capture}
					<pubDate>{$smarty.const.DATE_RSS|date:$datePublished}</pubDate>
				{/if}
			</item>
		{/foreach}{* articles *}
	</channel>
</rss>
