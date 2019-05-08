{**
 * controllers/notification/errorNotificationContent.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display error notification content.
 *}
{foreach item=message from=$errors}
	<ul>
		<li>{$message|escape}</li>
	</ul>
{/foreach}
