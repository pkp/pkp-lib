{**
 * lib/pkp/templates/workflow/version.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Version view for workflow stage production
 *}

<div class="pkp_panel_wrapper">

	<div class="pkp_context_sidebar">

		{** edit metadata of this version **}
		<div id="editMetadataDiv" class="pkp_tab_actions">
			<ul class="pkp_workflow_decisions">
				<li>{include file="linkAction/linkAction.tpl" action=$editMetadataLinkAction}</li>
			</ul>
		</div>

		{** TODO: identifiers **}
		
		{** schedule for publication **}
		{if array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), (array)$userRoles)}
			<div id="schedulePublicationDiv" class="pkp_tab_actions">
				<ul class="pkp_workflow_decisions">
					<li>{include file="linkAction/linkAction.tpl" action=$schedulePublicationLinkAction}</li>
				</ul>
			</div>
		{/if}

		{** stage participants **}
		{include file="controllers/tab/workflow/stageParticipants.tpl"}

	</div>

	<div class="pkp_content_panel">
	
		{** production ready files **}
		{url|assign:productionReadyFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.productionReady.ProductionReadyFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$stageId escape=false}
		{load_url_in_div id="productionReadyFilesGridDiv" url=$productionReadyFilesGridUrl} 

		{** galleys **}
		{url|assign:representationsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.articleGalleys.ArticleGalleyGridHandler" op="fetchGrid" submissionId=$submission->getId() submissionRevision=$submissionRevision escape=false}
		{load_url_in_div id="formatsGridContainer"|uniqid url=$representationsGridUrl}

	</div>
</div>
