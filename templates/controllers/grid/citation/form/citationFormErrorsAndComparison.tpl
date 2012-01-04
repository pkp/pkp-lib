{**
 * citationFormErrorsAndComparison.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * A part of the citation form that will be refreshed
 * whenever the user changes one of the fields (by manual
 * edit or via a database query). Can be used stand-alone
 * or when refreshing the form as a whole.
 *}

{* create the before/after markup versions of the citations from the citation diff *}
{capture assign=rawCitationWithMarkup}{strip}
	{foreach from=$citationDiff item=change}
		{foreach from=$change key=changeType item=text}
			{* The raw citation gets common strings and deletions *}
			{if $changeType <= 0}
				<span class="citation-comparison-{if $changeType == 0}common{elseif $changeType == -1}deletion{/if}">{$text}</span>
			{/if}
		{/foreach}
	{/foreach}
{/strip}{/capture}
{capture assign=generatedCitationWithMarkup}{strip}
	{foreach from=$citationDiff item=change}
		{foreach from=$change key=changeType item=text}
			{* The generated citation gets common strings and additions *}
			{if $changeType >= 0}
				<span class="citation-comparison-{if $changeType == 0}common{elseif $changeType == 1}addition{/if}">{$text}</span>
			{/if}
		{/foreach}
	{/foreach}
{/strip}{/capture}
<script type="text/javascript">
	$(function() {ldelim}
		//
		// Initial setup
		//
		// Initial setup depends on whether we add or
		// edit a citation.
		{if $citation->getId()}
			// Hide editable raw citation on startup unless we're adding a
			// new citation.
			$('#editableRawCitation').hide();
		{else}
			// Hide the citation comparison markup instead when we add a new
			// citation.
			$('.citation-comparison').hide();
		{/if}


		//
		// Handle form messages
		//
		// "Click to dismiss message" feature. Must be done
		// with live as we use JS to insert messages sometimes.
		$('#citationFormMessages li').die('click').live('click', function() {ldelim}
			$(this).remove();
			if($('#citationFormMessages .formErrorList').children().length === 0) {ldelim}
				$('#citationFormMessages').remove();
			{rdelim}
		{rdelim});

		
		//
		// Handle raw citation edition
		//
		// Clicking on the raw citation should make it editable.
		$('#rawCitationWithMarkup div.value, #rawCitationWithMarkup .actions a.edit').each(function() {ldelim}
			$(this).click(function() {ldelim}
				$('#rawCitationWithMarkup').hide();
				$editableRawCitation = $('#editableRawCitation').show();
				$textarea = $editableRawCitation.find('textarea').focus();
				
				// Save original value for undo
				$textarea.data('original-value', $textarea.val());
				return false;
			{rdelim});
		{rdelim});

		// Handle expert settings
		extrasOnDemand('#rawCitationEditingExpertOptions');
		
		// Handle abort raw citation editing.
		$('#cancelRawCitationEditing').click(function() {ldelim}
			$editableRawCitation = $('#editableRawCitation').hide();
			$('#rawCitationWithMarkup').show();

			// Restore original raw citation value.
			$textarea = $editableRawCitation.find('textarea');
			$textarea.val($textarea.data('original-value'));
			return false;
		{rdelim}); 

		// Open a confirmation dialog when the user
		// clicks the "process raw citation" button.
		$('#processRawCitation')
			// modalConfirm() doesn't remove prior events
			// so we do it ourselves.
			.die('click')
			// Activate a throbber when the button is clicked.
			.click(function() {ldelim}
				// Throbber for raw citation processing.
				actionThrobber('#{$containerId}');
			{rdelim});
		
		// Configure the dialog.
		modalConfirm(
			'{url op="updateRawCitation"}',
			'replace',
			'#{$containerId}', 
			'{translate key="submission.citations.editor.details.processRawCitationWarning"}',
			[
				'{translate key="submission.citations.editor.details.processRawCitationGoAhead"}',
				'{translate key="common.cancel"}'
			],
			'#processRawCitation',
			'{translate key="submission.citations.editor.details.processRawCitationTitle"}',
			true
		);
		
		//
		// Handle field level data changes.
		//
		// Register event handler for refresh of the citation
		// comparison and message part of the form.
		ajaxAction(
			'post',
			'#citationFormErrorsAndComparison',
			// We bind the wrapper to a custom event. This can
			// be manually triggered if we want to refresh the
			// interface for some reason.
			'#citationFormErrorsAndComparison',
			'{url op="fetchCitationFormErrorsAndComparison"}',
			null,
			'refresh',
			'#editCitationForm'
		);
		
		// Bind citation fields with live to the refresh event
		// so that new fields will be automatically active. 
		$('.citation-field').die('change').live('change', function() {ldelim}
			$('#citationFormErrorsAndComparison').triggerHandler('refresh');
		{rdelim});
	{rdelim});
</script>
<div id="citationFormErrorsAndComparison" class="form-block">
	{if $unsavedChanges || $isError}
		<div id="citationFormMessages" class="help-message" title="{translate key="submission.citations.editor.details.clickToDismissMessage"}">
			<div id="formErrors">
				<p>
					<span class="formError">{translate key="submission.citations.editor.details.messages"}:</span>
					<ul class="formErrorList">
						{if $unsavedChanges}
							<li class="unsaved-data-warning">{translate key="submission.citations.editor.details.unsavedChanges"}</li>
						{/if}
						{if $isError}
							{foreach key=field item=message from=$errors}
								<li>{$message}</li>
							{/foreach}
						{/if}
					</ul>
				</p>
			</div>
		</div>
	{/if}
	

	{* We have two versions of the raw citation - one editable and the
	   other with mark-up for comparison. We use JS to switch between the
	   two on user demand. *}
	<div id="editableRawCitation">
		<div class="label">
			{if $citation->getId()}
				{fieldLabel name="rawCitation" key="submission.citations.editor.details.rawCitation"}
			{else}
				{fieldLabel name="rawCitation" key="submission.citations.editor.citationlist.newCitation"}
			{/if}
		</div>
		<div class="value">
			<textarea class="textarea" validation="required" id="rawCitation" name="rawCitation" rows="5">{$rawCitation}</textarea>
		</div>
		{if $citation->getId()}
			<div id="rawCitationEditingExpertOptions">
				<div class="options-head">
					<span class="ui-icon"></span>
					<span class="option-block-inactive">{translate key="submission.citations.editor.details.editRawCitationExpertSettingsInactive"}</span>
					<span class="option-block-active">{translate key="submission.citations.editor.details.editRawCitationExpertSettingsActive"}</span>
				</div>
				{include file="controllers/grid/citation/form/citationFilterOptionBlock.tpl"
					titleKey="submission.citations.editor.details.editRawCitationExtractionServices"
					availableFilters=$availableParserFilters}
			</div>
			<div class="form-block actions">
				<button id="cancelRawCitationEditing" type="button">{translate key="common.cancel"}</button>
				<button id="processRawCitation" type="button">{translate key="submission.citations.editor.details.processRawCitation"}</button>
			</div>
			<div class="clear"></div>
		{/if}
	</div>
	<div id="rawCitationWithMarkup" class="citation-comparison">
		<div class="label">{translate key="submission.citations.editor.details.rawCitation"}</div>
		<div class="actions">
			<a class="edit" title="{translate key="submission.citations.editor.clickToEdit"}" href=""></a>
		</div>
		<div class="value ui-corner-all" title="{translate key="submission.citations.editor.clickToEdit"}">{$rawCitationWithMarkup}</div>
	</div>
	<div id="generatedCitationWithMarkup" class="citation-comparison">
		<div class="label">{translate key="submission.citations.editor.details.citationExportPreview"} ({$currentOutputFilter})</div>
		<div class="value ui-corner-all">{$generatedCitationWithMarkup}</div>
	</div>
</div>
