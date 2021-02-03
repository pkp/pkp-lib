{**
 * templates/controllers/api/file/editMetadata.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The "edit proof submission file" tabset.
 *}
<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#editFileMetadataTabs').pkpHandler('$.pkp.controllers.TabHandler');
	{rdelim});
</script>
<div id="editFileMetadataTabs">
	<ul>
		<li><a href="{url router=$smarty.const.ROUTE_COMPONENT op="editMetadataTab" submissionFileId=$submissionFile->getId() submissionId=$submissionFile->getData('submissionId') stageId=$stageId}">{translate key="grid.action.editMetadata"}</a></li>
		{if $showIdentifierTab}
			<li><a href="{url router=$smarty.const.ROUTE_COMPONENT op="identifiers" submissionFileId=$submissionFile->getId() submissionId=$submissionFile->getData('submissionId') stageId=$stageId}">{translate key="submission.identifiers"}</a></li>
		{/if}
	</ul>
</div>
