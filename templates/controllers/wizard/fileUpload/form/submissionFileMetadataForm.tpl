{**
 * templates/controllers/wizard/fileUpload/form/submissionFileMetadataForm.tpl
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Mounts the SubmissionFileMetadataForm Vue component.
 *}
{assign var=metadataMountId value="submissionFileMetadata-"|cat:$submissionFile->getId()}
{capture assign=metadataSaveUrl}{url component="api.file.ManageFileApiHandler" op="saveMetadata" submissionId=$submissionFile->getData('submissionId') stageId=$stageId reviewRoundId=$reviewRoundId fileStage=$submissionFile->getData('fileStage') submissionFileId=$submissionFile->getId() escape=false}{/capture}

<div id="{$metadataMountId}-container" data-submission-file-metadata-wrapper>
	<div id="{$metadataMountId}" class="pkpForm"></div>

	<script type="text/javascript">
		(function() {ldelim}
			// FieldRichTextarea looks for the skin URL on pkp.tinyMCE.
			// The main app sets it as $root state; our standalone mount
			// doesn't see that, so populate the global fallback.
			if (!pkp.tinyMCE) {ldelim}
				pkp.tinyMCE = {ldelim}skinUrl: {$tinyMceSkinUrl|json_encode}{rdelim};
			{rdelim}

			var mountId = {$metadataMountId|json_encode};
			var containerId = mountId + '-container';
			var rootProps = $.extend({ldelim}{rdelim}, {$metadataMountConfig|json_encode}, {ldelim}
				saveUrl: {$metadataSaveUrl|json_encode},
				onSaved: function() {ldelim}
					$('#' + containerId).trigger('formSubmitted');
				{rdelim},
				onCancelled: function() {ldelim}
					$('#' + containerId).trigger('formCanceled');
				{rdelim}
			{rdelim});

			var app = pkp.pkpCreateVueApp(
				pkp.controllers.SubmissionFileMetadataForm,
				rootProps
			);
			app.mount('#' + mountId);

			// Unmount on DOM detach so TinyMCE editors are removed from
			// the global registry before the next mount reuses their ids.
			var mountEl = document.getElementById(mountId);
			if (mountEl && typeof MutationObserver !== 'undefined') {ldelim}
				var observer = new MutationObserver(function() {ldelim}
					if (!document.body.contains(mountEl)) {ldelim}
						observer.disconnect();
						try {ldelim} app.unmount(); {rdelim} catch (e) {ldelim} {rdelim}
					{rdelim}
				{rdelim});
				observer.observe(document.body, {ldelim}childList: true, subtree: true{rdelim});
			{rdelim}
		{rdelim})();
	</script>

	{if $supportsDependentFiles}
		{capture assign=dependentFilesGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.files.dependent.DependentFilesGridHandler" op="fetchGrid" submissionId=$submissionFile->getData('submissionId') submissionFileId=$submissionFile->getId() stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
		<div class="p-8 border-t border-light/50">
			{load_url_in_div id="dependentFilesGridDiv" url=$dependentFilesGridUrl}
		</div>
	{/if}
</div>
