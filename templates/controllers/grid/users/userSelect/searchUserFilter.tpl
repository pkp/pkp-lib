{**
 * templates/controllers/grid/user/userSelect/searchUserFilter.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Displays the form to filter results in the userSelect grid.
 *
 *}
{assign var="formId" value="searchUserFilter-"|concat:$filterData.gridId}
<script type="text/javascript">
	// Attach the form handler to the form.
	$('#{$formId}').pkpHandler('$.pkp.controllers.form.ClientFormHandler',
		{ldelim}
			trackFormChanges: false
		{rdelim}
	);
</script>
<form class="pkp_form filter" id="{$formId}" action="{url op="fetchGrid"}" method="post">
	{csrf}
	{fbvFormArea id="userSearchFormArea"|concat:$filterData.gridId}
		<input type="hidden" name="submissionId" value="{$filterData.submissionId|escape}" />
		<input type="hidden" name="stageId" value="{$filterData.stageId|escape}" />
		<input type="hidden" name="userGroupid" value="{$filterData.userGroupId|escape}" />
		{fbvFormSection title="manager.userSearch.searchByName"}
			{fbvElement type="text" name="name" id="name"|concat:$filterData.gridId value=$filterSelectionData.name}
		{/fbvFormSection}
		{fbvFormSection class="pkp_helpers_text_right"}
			{fbvElement type="submit" id="submitFilter" label="common.search"}
		{/fbvFormSection}
	{/fbvFormArea}
</form>
