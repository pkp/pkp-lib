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
<div class="readQuery">
	{url|assign:queryNotesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueryNotesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId queryId=$query->getId() escape=false}
	{load_url_in_div id="queryNotesGrid" url=$queryNotesGridUrl}

	{url|assign:queryNoteFormUrl router=$smarty.const.ROUTE_COMPONENT component="grid.queries.QueryNotesGridHandler" op="addNote" submissionId=$submission->getId() stageId=$stageId queryId=$query->getId() escape=false}
	{load_url_in_div id="queryNoteForm" url=$queryNoteFormUrl}
</div>
