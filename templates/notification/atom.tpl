{**
 * atom.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Atom feed template
 *
 * $Id$
 *}
<?xml version="1.0" encoding="{$defaultCharset|escape}"?>
<feed xmlns="http://www.w3.org/2005/Atom">
	<id>{$selfUrl}</id>
	<title>{$siteTitle} {translate key="notification.notifications"}</title>

	<link rel="self" type="application/atom+xml" href="{$selfUrl}" />

	<generator uri="http://pkp.sfu.ca/ojs/" version="{$version|escape}">{translate key=$appName}</generator>

{foreach from=$notifications item=notification}
	<entry>
		<id>{$notification->getId()}</id>
		<title>{translate key="notification.notification"} : {$notification->getDateCreated()|date_format:"%a, %d %b %Y %T %z"}</title>
		{if $notification->getLocation() != null}
			<link rel="alternate" href="{$notification->getLocation()}" />
		{else}
			<link rel="alternate" href="{url page="notification"}" />
		{/if}

		<summary type="html" xml:base="{if $notification->getLocation() != null}{$notification->getLocation()}{else}{url page="notification"}{/if}">
			{if $notification->getIsLocalized()}
				{translate key=$notification->getContents() param=$notification->getParam()}
			{else}
				{$notification->getContents()}
			{/if}
		</summary>

		<published>{$notification->getDateCreated()|date_format:"%Y-%m-%dT%T%z"|regex_replace:"/00$/":":00"}</published>
	</entry>
{/foreach}
</feed>


