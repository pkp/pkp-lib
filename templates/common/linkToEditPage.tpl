{**
 * templates/common/linkToEditPage.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Add a link using the passed url with the edit icon and text if current
 * user is a press manager.
 *}
{if in_array(ROLE_ID_MANAGER, (array) $userRoles)}
	<div class="pkp_helpers_align_right pkp_helpers_icon_link_valign linkToEditPage">
		<a href="{$editUrl}" class="sprite edit" target="_blank" title="{translate key="help.goToEditPage"}">{translate key="common.edit"}</a>
	</div>
	<div class="pkp_helpers_clear"></div>
{/if}
