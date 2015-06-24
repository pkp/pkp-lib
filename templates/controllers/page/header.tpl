{**
 * templates/controllers/page/header.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Common site header div contents.
 *}
<script type="text/javascript">
	// Initialise JS handler.
	$(function() {ldelim}
		$('#headerNavigationContainer').pkpHandler(
			'$.pkp.pages.header.HeaderHandler',
			{ldelim}
				requestedPage: '{$requestedPage|escape:"javascript"}',
				fetchUnreadNotificationsCountUrl: '{url|escape:javascript router=$smarty.const.ROUTE_COMPONENT component="grid.notifications.NotificationsGridHandler" op="getUnreadNotificationsCount"}'
			{rdelim});
	{rdelim});
</script>
{include file="header/sitenav.tpl"}
