{**
 * controllers/notification/errorNotificationContent.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display error notification content.
 *}
{foreach item=message from=$errors}
	<ul>
		<li>{$message|escape}</li>
	</ul>
{/foreach}
