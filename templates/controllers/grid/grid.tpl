{**
 * templates/controllers/grid/grid.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Grid HTML markup and construction
 *}

{assign var=staticId value="component-"|concat:$grid->getId()}
{assign var=gridId value=$staticId|concat:'-'|uniqid}
{assign var=gridTableId value=$gridId|concat:"-table"}
{assign var=gridActOnId value=$gridTableId}

<script type="text/javascript">
	$(function() {ldelim}
		$('#{$gridId|escape:javascript}').pkpHandler(
			'{$grid->getJSHandler()|escape:javascript}',
			{ldelim}
				gridId: '{$grid->getId()|escape:javascript}',
				{foreach from=$grid->getUrls() key=key item=itemUrl name=gridUrls}
					{$key|escape:"javascript"}: '{$itemUrl|escape:"javascript"}',
				{/foreach}
				bodySelector: '#{$gridActOnId|escape:javascript}',
				{if $grid->getPublishChangeEvents()}
					publishChangeEvents: [
						{foreach from=$grid->getPublishChangeEvents() item=gridPublishChangeEvent name=gridPublishChangeEvents}{if $smarty.foreach.gridPublishChangeEvents.first}'{else}', '{/if}{$gridPublishChangeEvent|escape:"javascript"}{if $smarty.foreach.gridPublishChangeEvents.last}'{/if}{/foreach}
					],
				{/if}
				features: {include file='controllers/grid/feature/featuresOptions.tpl' features=$features}
			{rdelim}
		);
	{rdelim});
</script>

<div id="{$gridId|escape}" class="pkp_controllers_grid">
	{if !$grid->getIsSubcomponent()}<div class="wrapper">{/if}
		{include file="controllers/grid/gridHeader.tpl"}
		<table id="{$gridTableId|escape}">
			<colgroup>
				{foreach from=$columns item=column}<col {if $column->hasFlag('indent')}class="indent_col"{/if}/>{/foreach}
			</colgroup>
			<thead>
				{** build the column headers **}
				<tr>
					{foreach name=columns from=$columns item=column}
						{if $column->hasFlag('alignment')}
							{assign var=alignment value=$column->getFlag('alignment')}
						{else}
							{assign var=alignment value=$smarty.const.COLUMN_ALIGNMENT_CENTER}
						{/if}
						<th scope="col" style="text-align: {$alignment};{if $column->hasFlag('width')} width: {$column->getFlag('width')}%{/if}">
							{$column->getLocalizedTitle()}
							{* TODO: Remove this stuff.  Actions should not ever appear in the TH of a grid. *}
							{if $smarty.foreach.columns.last && $grid->getActions($smarty.const.GRID_ACTION_POSITION_LASTCOL)}
								<span class="options pkp_linkActions">
									{foreach from=$grid->getActions($smarty.const.GRID_ACTION_POSITION_LASTCOL) item=action}
										{if is_a($action, 'LegacyLinkAction')}
											{if $action->getMode() eq $smarty.const.LINK_ACTION_MODE_AJAX}
												{assign var=actionActOnId value=$action->getActOn()}
											{else}
												{assign var=actionActOnId value=$gridActOnId}
											{/if}
											{include file="linkAction/legacyLinkAction.tpl" action=$action id=$gridId actOnId=$actionActOnId hoverTitle=true}
										{else}
											{include file="linkAction/linkAction.tpl" action=$action contextId=$staticId}
										{/if}
									{/foreach}
								</span>
							{/if}
						</th>
					{/foreach}
				</tr>
			</thead>
			{if $grid->getIsSubcomponent()}
				{* Create two separate tables so that the body part
				   can be scrolled independently from the header in a
				   cross-browser compatible way using only CSS. *}
				</table>
				<div class="scrollable">
				<table>
			{/if}
			{foreach from=$gridBodyParts item=bodyPart}
				{$bodyPart}
			{foreachelse}
				<tbody><tr></tr></tbody>
			{/foreach}
			<tbody class="empty"{if count($gridBodyParts) > 0} style="display: none;"{/if}>
				{**
					We need the last (=empty) line even if we have rows
					so that we can restore it if the user deletes all rows.
				**}
				<tr>
					<td colspan="{$columns|@count}">{translate key=$grid->getEmptyRowText()}</td>
				</tr>
			</tbody>
		</table>
		{if $grid->getIsSubcomponent()}
			</div>
		{/if}
		{include file="controllers/grid/gridActionsBelow.tpl" actions=$grid->getActions($smarty.const.GRID_ACTION_POSITION_BELOW) gridId=$staticId}
		{if $grid->getFootNote()}
			<p class="pkp_grid_description">{translate key=$grid->getFootNote()}</p>
		{/if}
	{if !$grid->getIsSubcomponent()}</div>{/if}
	<div class="pkp_helpers_clear"></div>
</div>
