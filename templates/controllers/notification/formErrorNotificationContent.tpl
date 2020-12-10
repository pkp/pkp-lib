{**
 * controllers/notification/formErrorNotificationContent.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display in place notifications.
 *}
{foreach key=field item=message from=$errors}
	<a href="#{$field|escape}">{$message}</a><br />
{/foreach}
