{**
 * templates/controllers/page/frontend/usernav.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User-context menu for the frontend
 *}
<script type="text/javascript">
	// Initialise JS handler.
	$(function() {ldelim}
		$('#headerNavigationContainer').pkpHandler(
			'$.pkp.pages.header.HeaderHandler',
			{ldelim}
				requestedPage: {$requestedPage|json_encode},
			{rdelim});
	{rdelim});
</script>
{include file="header/frontend/usernav.tpl"}
