{**
 * templates/submission/form/categoriesFilter.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Categories filter for submissions.
 *}
<script>
	// Initialise JS handler.
	$(function() {ldelim}
		$('#categoriesFilter').pkpHandler(
			'$.pkp.pages.submission.SubmissionCategoriesFilterHandler'
		);
	{rdelim});
</script>

<div class="pkp_categories_filter" id="categoriesFilter">
	<input type="text" name="searchCategories" id="searchCategories" placeholder="{translate key="common.search"}">
	<label for="searchCategories" class="-screenReader">
		{translate key="common.search"}
	</label>
    <div class="categories_list">
        <div class="unassigned_categories">
            {foreach from=$categories item="category" key="id"}
                {if !in_array($id, $assignedCategories)}
                    {fbvElement type="checkbox" id="categories[]" value=$id checked=in_array($id, $assignedCategories) label=$category translate=false}
                {/if}
            {/foreach}
        </div>
        <div class="assigned_categories">
            {foreach from=$categories item="category" key="id"}
                {if in_array($id, $assignedCategories)}
                    {fbvElement type="checkbox" id="categories[]" value=$id checked=in_array($id, $assignedCategories) label=$category translate=false}
                {/if}
            {/foreach}
        </div>
    </div>
</div>
