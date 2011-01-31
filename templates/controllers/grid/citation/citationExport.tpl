
{**
 * citationExport.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Formatted citation export
 *}
<div id="citationEditorExportCanvas" class="canvas">
	<div id="citationEditorExportPane" class="pane text-pane">
		{if $errorMessage}
			<div class="help-message">
				{$errorMessage}
			</div>
		{else}
			<script type="text/javascript">
				$(function() {ldelim}
					// Activate the export filter selection drop-down boxes.
					$('#exportCitationForm .select')
						.change(function() {ldelim}
							// Check whether a filter has been selected.
							// (The regex below deliberately excludes
							// the default value '-1').
							var filterId = $(this).val();
							if (/^[0-9]+$/.test(filterId)) {ldelim}
								var $throbberId = $('#citationEditorExportPane');
								$throbberId.triggerHandler('actionStart');
								$.post(
									'{url op="exportCitations"}',
									{ldelim}
										'assocId': {$assocId},
										'filterId': filterId
									{rdelim},
									function(jsonData) {ldelim}
										$throbberId.triggerHandler('actionStop');
										if (jsonData !== null && jsonData.status === true) {ldelim}
											$('#citationEditorExportCanvas').replaceWith(jsonData.content);
										{rdelim}
									{rdelim},
									'json'
								);
							{rdelim} else {ldelim}
								$(this).val($(this).data('original-value'));
								alert('{translate key="submission.citations.editor.export.filterSelectionError"}');
							{rdelim}
						{rdelim})
						.each(function() {ldelim}
							// Save the original selection.
							$(this).data('original-value', $(this).val()); 
						{rdelim});

					// Activate throbber for tab reloading.
					actionThrobber('#citationEditorExportPane');

					// Activate the "select all" link.
					$('#markForExport').click(function() {ldelim}
						$('#citationEditorExportPane .scrollable').selectRange();
						return false;
					{rdelim});
				{rdelim});
			</script>
			<form name="exportCitationForm" id="exportCitationForm" method="post" action="" >
				<br />
				<p>
					<p>{translate key="submission.citations.editor.export.filterSelectionDescription"}</p>
					<select class="field select" name="filterId" id="xmlExportFilterSelect">
						<option value="-1">{translate key="submission.citations.editor.export.pleaseSelectXmlFilter"}</option>
						{html_options options=$xmlExportFilters selected=$exportFilterId}
						<option value="-1">&nbsp;</option>
						<option value="-1">{translate key="submission.citations.editor.export.pleaseSelectPlaintextFilter"}</option>
						{html_options options=$textExportFilters selected=$exportFilterId}
					</select>
				</p>
			</form>
		
			{if $exportOutput}
				<p>{translate key="submission.citations.editor.export.exportDescription"}</p>
				
				<div class="scrollable">	
					--<p/>
					{if $exportFilterType == 'xml'}
						<pre>{$exportOutput|escape:htmlall}</pre>
					{else}
						{$exportOutput}
					{/if}
					--
				</div>
			{/if}
		{/if}
	</div>
</div>

{* FIXME: Move to references list filter
{foreach from=$formattedCitations key=citationIndex item=formattedCitation}
	<a name="c{$citationIndex+1}_{$formattedCitation|strip_tags|truncate:50:'':false|regex_replace:'/[ ,.;:()]+/':'_'}" ></a>{$formattedCitation}
{/foreach *}

