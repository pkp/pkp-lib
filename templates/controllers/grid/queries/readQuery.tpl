{**
 * templates/controllers/grid/queries/readQuery.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Read a query.
 *
 *}
<script>
	$(function() {ldelim}
		$('#readQueryContainer').pkpHandler(
			'$.pkp.controllers.grid.queries.ReadQueryHandler',
			{ldelim}
				fetchNoteFormUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component=$queryNotesGridHandlerName op="addNote" params=$requestArgs queryId=$query->getId() escape=false},
				fetchParticipantsListUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="participants" params=$requestArgs queryId=$query->getId() escape=false}
			{rdelim}
		);
	{rdelim});
</script>

<div id="readQueryContainer" class="pkp_controllers_query">
    <h4>
        {translate key="editor.submission.stageParticipants"}
		{if $editAction}
			{include file="linkAction/linkAction.tpl" action=$editAction contextId="editQuery"}
		{/if}
    </h4>
    <ul id="participantsListPlaceholder" class="participants"></ul>

	{capture assign=queryNotesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component=$queryNotesGridHandlerName op="fetchGrid" params=$requestArgs queryId=$query->getId() escape=false}{/capture}
	{load_url_in_div id="queryNotesGrid" url=$queryNotesGridUrl}

	<div class="queryEditButtons">
		<div class="openNoteForm add_note">
	    	<a href="#">
	        	{translate key="submission.query.addNote"}
			</a>
		</div>
		<div class="leaveQueryForm leave_query" {if !$showLeaveQueryButton}style="display: none;"{/if}">
			{include file="linkAction/linkAction.tpl" action=$leaveQueryLinkAction contextId="leaveQueryForm"}
		</div>
		<div class="pkp_spinner"></div>
	</div>

	<div id="newNotePlaceholder"></div>
</div>
