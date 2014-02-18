{**
 * lib/pkp/templates/controllers/page/sidebar.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Common site sidebar.
 *}
{call_hook|assign:"leftSidebarCode" name="Templates::Common::LeftSidebar"}
{call_hook|assign:"rightSidebarCode" name="Templates::Common::RightSidebar"}
{if !$leftSidebarCode && !$rightSidebarCode}
	{* Temporary fix for #7258 *}
	<div class="pkp_structure_nosidebar">
	</div>
{/if}

{if $leftSidebarCode}
	<div class="pkp_structure_sidebar pkp_structure_sidebar_left mod simple">
		{$leftSidebarCode}
	</div><!-- pkp_structure_sidebar_left -->
{/if}
{if $rightSidebarCode}
	<div class="pkp_structure_sidebar pkp_structure_sidebar_right mod simple">
		{$rightSidebarCode}
	</div><!-- pkp_structure_sidebar_right -->
{/if}
