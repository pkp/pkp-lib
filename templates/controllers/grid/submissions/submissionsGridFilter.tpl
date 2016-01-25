{**
 * templates/controllers/grid/submissions/submissionsGridFilter.tpl
 *
 * Copyright (c) 2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Filter template for submissions lists.
 *}
{assign var="formId" value="submissionsListFilter-"|concat:$filterData.gridId}
<script type="text/javascript">
	// Attach the form handler to the form.
	$('#{$formId}').pkpHandler('$.pkp.controllers.form.ClientFormHandler',
		{ldelim}
			trackFormChanges: false
		{rdelim}
	);
</script>
<form class="pkp_form filter" id="{$formId}" action="{url op="fetchGrid"}" method="post">
	{fbvFormArea id="submissionSearchFormArea"|concat:$filterData.gridId}
		{fbvFormSection}
			{fbvElement type="select" name="column" id="column"|concat:$filterData.gridId from=$filterData.columns selected=$filterSelectionData.column size=$fbvStyles.size.SMALL translate=false inline="true"}
			{fbvElement type="select" name="stageId" id="stageId"|concat:$filterData.gridId from=$filterData.workflowStages selected=$filterSelectionData.stageId size=$fbvStyles.size.SMALL translate=true inline="true"}
			{fbvElement type="text" name="search" id="search"|concat:$filterData.gridId value=$filterSelectionData.search size=$fbvStyles.size.MEDIUM inline="true"}
		{/fbvFormSection}
		{* Buttons generate their own section *}
		{fbvFormButtons hideCancel=true submitText="common.search"}
	{/fbvFormArea}
</form>
