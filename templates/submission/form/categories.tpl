{**
 * templates/submission/form/categories.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Include categories for submissions.
 *}
{if $hasCategories}
	{if $readOnly}
		{fbvFormSection title="grid.category.categories" list=true}
			{foreach from=$assignedCategories item=category}
				<li>{$category->getLocalizedTitle()|escape}</li>
			{/foreach}
		{/fbvFormSection}
	{else}
		{fbvFormSection}
			{assign var="uuid" value=""|uniqid|escape}
			<div id="categories-{$uuid}">
				<list-panel
					v-bind="components.categories"
					@set="set"
				/>
			</div>
			<script type="text/javascript">
				pkp.registry.init('categories-{$uuid}', 'Container', {$categoriesListData|json_encode});
			</script>
		{/fbvFormSection}
	{/if}
{/if}
