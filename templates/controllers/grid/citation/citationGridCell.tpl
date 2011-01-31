{**
 * citationGridCell.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * A citation editor grid cell.
 *}
{assign var=cellId value="cell-"|concat:$id}
<span id="{$cellId}">
	{assign var=cellAction value=$actions[0]}
	{include file="linkAction/linkAction.tpl" id=$cellId|concat:"-action-":$cellAction->getId() action=$cellAction actOnId=$cellAction->getActOn() buttonId=$cellId}
	[{$citationSeq}] {$label|escape}
	<script type="text/javascript">
		$(function() {ldelim}
			$parentDiv = $('#{$cellId}').parent();

			// Format parent div.
			$parentDiv
				.attr('title', '{$cellAction->getLocalizedTitle()} [{if $isApproved}Approved{else}Not Approved{/if}]');

			// Mark the clickable row.
			$parentDiv.parent().addClass('clickable-row');
			
			// Mark the row as the current row.
			$parentDiv.parent().parent().parent()
				{if $isCurrentItem}.addClass('current-item'){/if}
				.addClass('{if !$isApproved}un{/if}approved-citation');
			
			// Copy click event to parent div.
			clickEventHandlers = $('#{$cellId}').data('events')['click'];
			for(clickEvent in clickEventHandlers) {ldelim}
				$parentDiv.click(clickEventHandlers[clickEvent].handler);
			{rdelim}
		{rdelim});
	</script>
</span>

