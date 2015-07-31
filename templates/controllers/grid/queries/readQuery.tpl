{**
 * templates/controllers/grid/queries/readQuery.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
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
				fetchNoteFormUrl: '{url|escape:"javascript" router=$smarty.const.ROUTE_COMPONENT component=$queryNotesGridHandlerName op="addNote" params=$requestArgs queryId=$query->getId() escape=false}',
				fetchParticipantsListUrl: '{url|escape:"javascript" router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueriesGridHandler" op="participants" params=$requestArgs queryId=$query->getId() escape=false}'
			{rdelim}
		);
	{rdelim});
</script>

<div id="readQueryContainer">
	<div id="queryParticipantsContainer">
		<h2 class="pkp_helpers_align_left">{translate key="editor.submission.stageParticipants"}</h2>
		{if $editAction}
			{include file="linkAction/linkAction.tpl" action=$editAction contextId="editQuery"}
		{/if}
		<ul class="pkp_helpers_clear" id="participantsListPlaceholder"></ul>
	</div>

	{url|assign:queryNotesGridUrl router=$smarty.const.ROUTE_COMPONENT component=$queryNotesGridHandlerName op="fetchGrid" params=$requestArgs queryId=$query->getId() escape=false}
	{load_url_in_div id="queryNotesGrid" url=$queryNotesGridUrl}

	{null_link_action id="openNoteForm" key="submission.query.addNote" image="add"}

	<div id="newNotePlaceholder"></div>
</div>
