{**
 * templates/controllers/grid/gridCategoryRow.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a regular grid row
 *}
{assign var=categoryId value="component-"|concat:$categoryRow->getGridId():"-category-":$categoryRow->getId()}
<td colspan="{$columns|@count}" class="pkp_linkActions">
	{if $categoryRow->getActions()}
		{foreach name=actions from=$categoryRow->getActions() item=action}
			{include file="linkAction/linkAction.tpl" action=$action contextId=$gridId}
		{/foreach}
	{/if}
	{$categoryRow->getCategoryLabel()}
</td>
