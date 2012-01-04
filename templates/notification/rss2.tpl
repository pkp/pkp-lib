{**
 * rss2.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * RSS 2 feed template
 *
 * $Id$
 *}
<?xml version="1.0" encoding="{$defaultCharset|escape}"?>
<rss version="2.0">
	<channel>
		{* required elements *}
		<title>{$siteTitle} {translate key="notification.notifications"}</title>
		<link>{$selfUrl}</link>

		{* optional elements *}
		<language>{$locale|replace:'_':'-'|strip|escape:"html"}</language>
		<generator>{translate key=$appName} {$version|escape}</generator>
		<docs>http://blogs.law.harvard.edu/tech/rss</docs>
		<ttl>60</ttl>

		{foreach from=$notifications item=notification}
			<item>
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
				<pubDate>{$notification->getDateCreated()|date_format:"%a, %d %b %Y %T %z"}</pubDate>
			</item>
		{/foreach}
	</channel>
</rss>
