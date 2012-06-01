{**
 * temlates/controllers/grid/gridBodyPartWithCategory.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a set of grid rows with a category row at the beginning
 *}
{** category id must be set by the rendering of the category row **}
<tbody id="{$categoryId|escape}" class="category_grid_body">
	<tr class="category{if $iterator} group{$iterator|escape}{/if}">
		{$renderedCategoryRow}
		{** the regular data rows **}
		{foreach from=$rows item=row}
			{$row}
		{/foreach}
	</tr>
	<tbody id="{$categoryId|concat:'-emptyPlaceholder'|escape}" class="empty"{if count($rows) > 0} style="display: none;"{/if}>
		{**
			We need the last (=empty) line even if we have rows
			so that we can restore it if the user deletes all rows.
		**}
		<tr>
			<td class="no_border indent_row"></td>
			<td colspan="{$grid->getColumnsCount('indent')}">{translate key=$grid->getEmptyCategoryRowText()}</td>
		</tr>
	</tbody>
</tbody>

