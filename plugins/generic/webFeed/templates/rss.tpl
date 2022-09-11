{**
 * plugins/generic/webFeed/templates/rss.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * RSS feed template
 *
 *}
<?xml version="1.0" encoding="{$defaultCharset|escape}"?>
<rdf:RDF
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
	xmlns="http://purl.org/rss/1.0/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:prism="http://prismstandard.org/namespaces/1.2/basic/"
	xmlns:cc="http://web.resource.org/cc/">

	<channel rdf:about="{url server=$server->getPath()}">
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
		{assign var="publisherInstitution" value=$server->getData('publisherInstitution')}
		{if $publisherInstitution}
			<dc:publisher>{$publisherInstitution|strip|escape:"html"}</dc:publisher>
		{/if}

		{if $server->getPrimaryLocale()}
			<dc:language>{$server->getPrimaryLocale()|replace:'_':'-'|strip|escape:"html"}</dc:language>
		{/if}

		<prism:publicationName>{$server->getLocalizedName()|strip|escape:"html"}</prism:publicationName>

		{if $server->getData('printIssn')}
			{assign var="ISSN" value=$server->getData('printIssn')}
		{elseif $server->getData('onlineIssn')}
			{assign var="ISSN" value=$server->getData('onlineIssn')}
		{/if}

		{if $ISSN}
			<prism:issn>{$ISSN|escape}</prism:issn>
		{/if}

		{if $server->getLocalizedData('licenseTerms')}
			<prism:copyright>{$server->getLocalizedData('licenseTerms')|strip|escape:"html"}</prism:copyright>
		{/if}

		<items>
			<rdf:Seq>
			{foreach name=sections from=$publishedSubmissions item=section key=sectionId}
				{foreach from=$section.articles item=article}
					<rdf:li rdf:resource="{url page="article" op="view" path=$article->getBestId()}"/>
				{/foreach}{* articles *}
			{/foreach}{* sections *}
			</rdf:Seq>
		</items>
	</channel>

{foreach name=sections from=$publishedSubmissions item=section key=sectionId}
	{foreach from=$section.articles item=article}
		{assign var=publication value=$article->getCurrentPublication()}
		<item rdf:about="{url page="article" op="view" path=$article->getBestId()}">

			{* required elements *}
			<title>{$article->getLocalizedTitle()|strip|escape:"html"}</title>
			<link>{url page="article" op="view" path=$article->getBestId()}</link>

			{* optional elements *}
			{if $article->getLocalizedAbstract()}
				<description>{$article->getLocalizedAbstract()|strip|escape:"html"}</description>
			{/if}

			{foreach from=$article->getCurrentPublication()->getData('authors') item=author name=authorList}
				<dc:creator>{$author->getFullName(false)|strip|escape:"html"}</dc:creator>
			{/foreach}

			<dc:rights>
				{translate|escape key="submission.copyrightStatement" copyrightYear=$article->getCopyrightYear() copyrightHolder=$article->getLocalizedCopyrightHolder()}
				{$article->getLicenseURL()|escape}
			</dc:rights>
			{if $publication->getData('accessStatus') == \APP\submission\Submission::ARTICLE_ACCESS_OPEN && $article->isCCLicense()}
				<cc:license rdf:resource="{$article->getLicenseURL()|escape}" />
			{else}
				<cc:license></cc:license>
			{/if}

			{if $article->getDatePublished()}
				<dc:date>{$article->getDatePublished()|date_format:"%Y-%m-%d"}</dc:date>
				<prism:publicationDate>{$article->getDatePublished()|date_format:"%Y-%m-%d"}</prism:publicationDate>
			{/if}

			{if $article->getPages()}
				{if $article->getStartingPage()}
					<prism:startingPage>{$article->getStartingPage()|escape}</prism:startingPage>
				{/if}
				{if $article->getEndingPage()}
					<prism:endingPage>{$article->getEndingPage()|escape}</prism:endingPage>
				{/if}
			{/if}

			{if $article->getStoredPubId('doi')}
				<prism:doi>{$article->getStoredPubId('doi')|escape}</prism:doi>
			{/if}
		</item>
	{/foreach}{* articles *}
{/foreach}{* sections *}

</rdf:RDF>
