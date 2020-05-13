{**
 * templates/submission/form/categories.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Include categories for submissions.
 *}
{if count($allCategories)}
	{if $readOnly}
		{fbvFormSection title="grid.category.categories" list=true}
			{foreach from=$allCategories item="category" key="id"}
				{if in_array($id, $assignCategories)}
					<li>{$category->getLocalizedTitle()|escape}</li>
				{/if}
			{/foreach}
		{/fbvFormSection}
	{else}
		{fbvFormSection list=true title="grid.category.categories"}
			{foreach from=$allCategories item="category" key="id"}
				{fbvElement type="checkbox" id="categories[]" value=$id checked=in_array($id, $assignedCategories) label=$category translate=false}
			{/foreach}
		{/fbvFormSection}
	{/if}
{/if}
