{**
 * templates/controllers/grid/settings/user/userGridFilter.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Filter template for user grid.
 *
 * @uses int|null $filterData.oldUserId The user grid is re-used when merging
 *   users. During the process, an $oldUserId is passed representing the user to
 *   be merged. This is used to distinguish the grid filter IDs. The $oldUserId
 *   must be submitted with the client form in order to ensure that the unique
 *   IDs are used when the grid is refreshed. This ensures the filter form
 *   binds to the correct grid when the filter is submitted and refreshed.
 *}
{assign var=filterId value="userSearchForm"}
{if $filterData.oldUserId}
	{assign var=filterId value=$filterId|concat:"-userMerge"}
{/if}
<script type="text/javascript">
	// Attach the form handler to the form.
	$('#{$filterId}').pkpHandler('$.pkp.controllers.form.ClientFormHandler',
		{ldelim}
			trackFormChanges: false
		{rdelim}
	);
</script>
<form class="pkp_form filter" id="{$filterId}" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.user.UserGridHandler" op="fetchGrid"}" method="post">
	{csrf}
	{if $filterData.oldUserId}
		<input type="hidden" name="oldUserId" value="{$filterData.oldUserId|escape}">
	{/if}
	{fbvFormArea id="userSearchFormArea"}
		{fbvFormSection title="common.search" for="search"}
			{fbvElement type="text" name="search" id="search" value=$filterSelectionData.search size=$fbvStyles.size.LARGE inline="true"}
			{fbvElement type="select" name="userGroup" id="userGroup" from=$filterData.userGroupOptions selected=$filterSelectionData.userGroup size=$fbvStyles.size.SMALL translate=false inline="true"}
		{/fbvFormSection}

		{fbvFormSection list=true}
			{if $filterSelectionData.includeNoRole}{assign var="checked" value="checked"}{/if}
			{fbvElement type="checkbox" name="includeNoRole" id="includeNoRole" value="1" checked=$checked label="user.noRoles.selectUsersWithoutRoles" translate="true"}
		{/fbvFormSection}
		{fbvFormButtons hideCancel=true submitText="common.search"}
	{/fbvFormArea}
</form>
