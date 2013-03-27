{**
 * templates/controllers/informationCenter/history.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display submission file history.
 *}

<script type="text/javascript">
	// Attach the Notes handler.
	$(function() {ldelim}
		$('#informationCenterHistoryTab').pkpHandler(
			'$.pkp.controllers.informationCenter.HistoryHandler',
			{ldelim}
				fetchHistoryUrl: '{url|escape:"javascript" router=$smarty.const.ROUTE_COMPONENT op="listHistory" params=$linkParams escape=false}',
				fetchPastHistoryUrl: '{url|escape:"javascript" router=$smarty.const.ROUTE_COMPONENT op="listPastHistory" params=$linkParams escape=false}'
			{rdelim}
		);
	{rdelim});
</script>

<div id="informationCenterHistoryTab">
	{if $showEarlierEntries}
	{**
	 * The file information center should provide access to log entries
	 * from previous stages. Does not apply to submissions.
	 *}
		<div id="historyAccordion">
			<h3><a href="#">{translate key="informationCenter.currentHistory"}</a></h3>
	{/if}

	{* Leave an empty div to be filled with log entries *}
	<div id="historyList">
	</div>
	{if $showEarlierEntries}
			<h3><a href="#" id="showPastHistoryLink">{translate key="informationCenter.pastHistory"}</a></h3>
			{* Leave an empty div to be filled in with past log entries *}
			<div id="pastHistoryList">
			</div>
		</div>
	{/if}
</div>
{*
</div>
*}
