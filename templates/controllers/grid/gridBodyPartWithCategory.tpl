{**
 * gridBodyPartWithCategory.tpl
 *
 * Copyright (c) 2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a set of grid rows with a category row at the beginning
 *}
{assign var=categoryId value="component-"|concat:$categoryRow->getGridId():"-category-":$categoryRow->getId()}
<tbody id="{$categoryId}">
	<tr class="category group{$gridCategoryNum}">
		<td colspan="{$numColumns}">
			{foreach name=actions from=$categoryRow->getActions() item=action}
				{include file="controllers/grid/gridAction.tpl" action=$action id=$categoryId}
				 | 
			{/foreach}
			{$categoryRow->getLabel()}
		</td>
		{** the regular data rows **}
		{foreach from=$rows item=row}
			{$row}
		{/foreach}	
	</tr>
</tbody>