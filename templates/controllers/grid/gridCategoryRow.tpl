{**
 * gridCategoryRow.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a regular grid row
 *}
{assign var=categoryId value="component-"|concat:$categoryRow->getGridId():"-category-":$categoryRow->getId()}
<td colspan="{$numColumns}">
{if $categoryRow->getActions()}
	{foreach name=actions from=$categoryRow->getActions() item=action}
		{include file="controllers/grid/gridAction.tpl" action=$action id=$categoryId}
	{/foreach}
{/if}
{$categoryRow->getCategoryLabel()}
</td>
