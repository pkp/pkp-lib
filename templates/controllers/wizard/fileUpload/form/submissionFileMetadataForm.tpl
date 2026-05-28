{**
 * templates/controllers/wizard/fileUpload/form/submissionFileMetadataForm.tpl
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Mounts the FileMetadataForm Vue component.
 *}
{assign var=metadataMountId value="submissionFileMetadata-"|cat:$submissionFile->getId()}

<div id="{$metadataMountId}-container" data-submission-file-metadata-wrapper>
	<div id="{$metadataMountId}" class="pkpForm"></div>

	<script type="text/javascript">
		(function() {ldelim}
			var mountId = {$metadataMountId|json_encode};
			var containerId = mountId + '-container';
			var rootProps = $.extend({ldelim}{rdelim}, {$metadataMountConfig|json_encode}, {ldelim}
				onSaved: function() {ldelim}
					$('#' + containerId).trigger('formSubmitted');
				{rdelim},
				onCancelled: function() {ldelim}
					$('#' + containerId).trigger('formCanceled');
				{rdelim}
			{rdelim});

			// Drop the old mount first so TinyMCE doesn't clash on remount.
			pkp.fileMetadataApps = pkp.fileMetadataApps || {ldelim}{rdelim};
			var previousApp = pkp.fileMetadataApps[mountId];
			if (previousApp) {ldelim}
				try {ldelim} previousApp.unmount(); {rdelim} catch (e) {ldelim} {rdelim}
			{rdelim}

			var app = pkp.pkpCreateVueApp(
				pkp.controllers.FileMetadataForm,
				rootProps
			);
			pkp.fileMetadataApps[mountId] = app;
			app.mount('#' + mountId);

			// Catch the case where the modal closes without re-running this script.
			var mountEl = document.getElementById(mountId);
			if (mountEl && typeof MutationObserver !== 'undefined') {ldelim}
				var observer = new MutationObserver(function() {ldelim}
					if (!document.body.contains(mountEl)) {ldelim}
						observer.disconnect();
						if (pkp.fileMetadataApps[mountId] === app) {ldelim}
							try {ldelim} app.unmount(); {rdelim} catch (e) {ldelim} {rdelim}
							delete pkp.fileMetadataApps[mountId];
						{rdelim}
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
