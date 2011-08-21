{**
 * controllers/notification/inPlaceNotification.tpl
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display in place notifications.
 *}
<ul>
{foreach key=field item=message from=$errors}
	<li><a href="#{$field|escape}">{$message}</a></li>
{/foreach}
</ul>