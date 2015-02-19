{**
 * citationGridCell.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * A citation editor grid cell.
 *}
{assign var=cellId value="cell-"|concat:$id}
<span id="{$cellId}" class="pkp_linkActions">
	{assign var=cellAction value=$actions[0]}
	{include file="linkAction/legacyLinkAction.tpl" id=$cellId|concat:"-action-":$cellAction->getId() action=$cellAction actOnId=$cellAction->getActOn() buttonId=$cellId}
	[{$citationSeq}] {$label|escape}
	<script type="text/javascript">
		<!--
		$(function() {ldelim}
			$parentDiv = $('#{$cellId}').parent();

			// Format parent div.
			$parentDiv
				.attr('title', '{$cellAction->getLocalizedTitle()} [{if $isApproved}{translate key="submission.citations.editor.citationlist.approved"}{else}{translate key="submission.citations.editor.citationlist.notApproved"}{/if}]');

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
		// -->
	</script>
</span>

