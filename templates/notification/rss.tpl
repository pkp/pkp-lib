{**
 * rss.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * RSS feed template
 *
 * $Id$
 *}
<?xml version="1.0" encoding="{$defaultCharset|escape}"?>
<rdf:RDF
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
	xmlns="http://purl.org/rss/1.0/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:prism="http://prismstandard.org/namespaces/1.2/basic/">

	<channel rdf:about="{$baseUrl}">
		<title>{$siteTitle} {translate key="notification.notifications"}</title>
		<link>{$selfUrl}</link>
		<language>{$locale|replace:'_':'-'|strip|escape:"html"}</language>
		<items>
			{foreach from=$notifications item=notification}
			<rdf:Seq>
				<rdf:li rdf:resource="{url page="notification"}"/>
			</rdf:Seq>
			{/foreach}
		</items>
	</channel>

	{foreach from=$notifications item=notification}
	<item rdf:about="{url page="notification"}">
		<title>{translate key="notification.notification"} : {$notification->getDateCreated()|date_format:"%a, %d %b %Y %T %z"}</title>
		<link>
			{if $notification->getLocation() != null}
				{$notification->getLocation()}
			{else}
				{url page="notification"}
			{/if}
		</link>
		<description>
			{if $notification->getIsLocalized()}
				{translate key=$notification->getContents() param=$notification->getParam()}
			{else}
				{$notification->getContents()}
			{/if}
		</description>
		<dc:creator>{$siteTitle|strip|escape:"html"}</dc:creator>
		<dc:date>{$notification->getDateCreated()|date_format:"%Y-%m-%d"}</dc:date>
	</item>
	{/foreach}

</rdf:RDF>
