{**
 * citationForm.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Citation grid form
 *}

{assign var=containerId value="citationEditorDetailCanvas"}
{assign var=formUid value="form"|uniqid}
<div id="{$containerId}" class="canvas">
	<script type="text/javascript">
		$(function() {ldelim}
			////////////////////////////////////////////////////////////
			// Form-level code.
			//
			// Create text to be inserted into the empty editor pane.
			emptyEditorText = '{strip}
				<div id="{$containerId}" class="canvas">
					<div class="wrapper scrollable">
						<div class="help-message">{translate|escape:javascript key="submission.citations.editor.details.pleaseClickOnCitationToStartEditing"}</div>
					</div>
				</div>
			{/strip}';

			// Handle deletion of the currently edited citation.
			{if $citation->getId()}
				$('#component-grid-citation-citationgrid-row-{$citation->getId()}').bind('updatedItem', function(event, actType) {ldelim}
					// Make sure that we clear the form panel when the corresponding
					// citation row is being deleted.
					$('#{$containerId}').replaceWith(emptyEditorText);
				{rdelim});
			{/if}

			////////////////////////////////////////////////////////////
			// Improvement options
			//
			// Create citation improvement tabs and make sure that
			// tabs are always fully visible when selected.
			$('#citationImprovement').tabs({ldelim}
				show: function() {ldelim}
					scrollToMakeVisible('#citationImprovementBlock');
				{rdelim}
			{rdelim});

			//
			// 1) Manual editing
			//
			// Store original label values so that we can
			// restore them if we have to cancel a label change.
			$('.citation-field-label').each(function() {ldelim}
				$(this).data('original-value', $(this).val());
			{rdelim});
					
			// Handle label change. This must be live() so
			// that adding of new fields is supported.
			$('.citation-field-label').die('change').live('change', function() {ldelim}
				var $this = $(this);
				var newName = $this.val();

				// Don't allow unsetting the label.
				if (newName === '-1') {ldelim}
					alert('{translate|escape:javascript key="submission.citations.editor.details.cannotSelectDefaultForLabel"}'); 
					$this.val($this.data('original-value'));
					return false;
				{rdelim}

				// Check whether another field currently
				// has that name set.
				$('.citation-field[name='+newName+']').each(function() {ldelim}
					var $this = $(this);

					// Reset the name of the input field
					// to something that doesn't clash
					// with existing fields.
					$this.attr('name', 'not-assigned[]');

					// Reset the corresponding label selector
					// and change it's color so that the user
					// easily identifies that this field has
					// been unset.
					$this.closest('tr').find('select').val(-1)
						.css('color', '#990000')
						.children().css('color', '#222222');
				{rdelim});

				// Reset the color of the label in
				// case we had marked it before.
				$this.css('color', '#222222');
				
				// Find the corresponding input field and
				// set its name attribute.
				$this.closest('tr').find('input')
					// Set the name to the chosen field name.
					.attr('name', newName)
					// Remove the "new-citation-field" class
					// in case this was a new field
					.removeClass('new-citation-field');

				// Trigger a change event so that the citation
				// comparison is being updated.
				$('#citationFormErrorsAndComparison').triggerHandler('refresh');
				
				// Store the new value for future reference.
				$this.data('original-value', newName);
			{rdelim});

			// Handle addition of new fields:
			// - Define helper function
			/**
			 * Add a new field for manual editing.
			 */
			function addNewCitationField() {ldelim}
				{capture assign=htmlForNewField}{include file="controllers/grid/citation/form/citationInputField.tpl" availableFields=$availableFields fieldName="new"}{/capture}
				var htmlForNewField = '{$htmlForNewField|escape:javascript}';
				var $newField = $('#citationImprovementManual tbody')
					.append(htmlForNewField).children().last();
				// Hide the label drop-down and the delete action
				// until the user enters a value
				$newField.find('a, select').hide();
				// Set the original value.
				$newField.find('select').data('original-value', '-1'); 
			{rdelim}
		
			// - Append the a first new input field to
			//   the field list.
			addNewCitationField();
			
			// - Remove help text and show field label
			//   selector on focus of the new field.
			$('.new-citation-field').die('focus').live('focus', function() {ldelim}
				var $this = $(this);
				if ($this.val() === '{translate|escape:javascript key="submission.citations.editor.details.newFieldInfo"}') {ldelim}
					$this
						// Empty the field.
						.val('')
						// Show label selector and delete button.
						.closest('tr').find('a, select').fadeIn(500);

					// Add new empty field to be edited next.
					addNewCitationField();
				{rdelim}
			{rdelim});
			
			// Handle deletion of fields.
			$('#citationImprovementManual .delete').die('click').live('click', function() {ldelim}
				// Remove the table row with that field.
				$(this).closest('tr').fadeOut(500, function() {ldelim}
					$(this).remove();
					// Trigger citation comparison refresh.
					$('#citationFormErrorsAndComparison').triggerHandler('refresh');
				{rdelim});
				return false;
			{rdelim});

			
			//
			// 2) Citation Services Query
			//
			// Bind action to citation service query button.
			ajaxAction(
				'post',
				'#{$containerId}',
				'#queryCitation',
				'{url op="checkCitation"}'
			);
				
			// Throbber when citation is re-queried.
			$('#queryCitation').click(function() {ldelim}
				actionThrobber('#{$containerId}');
			{rdelim});
			

			//
			// 3) Google Scholar
			//
			/**
			 * Return an Google Scholar query string
			 * based on NLM citation schema variable names.
			 *
			 * NB: The Google Scholar query string construction
			 * depends on the current metadata schema. That's why
			 * we separate it into it's own function. When we
			 * introduce further meta-data schemas we just have
			 * to provide other versions of this function and
			 * include it dynamically via smarty {ldelim}include{rdelim}
			 * based on the meta-data schema name.
			 *
			 * @return String
			 */
			function createMDSchemaSpecificGSQuery() {ldelim}
				var author = $('input[name=nlm30PersonGroupPersonGroupTypeAuthor]').val();
				var confName = $('input[name=nlm30ConfName]').val();
				var source = $('input[name=nlm30Source]').val();
				var articleTitle = $('input[name=nlm30ArticleTitle]').val();
				var doi = $('input[name=nlm30PubIdPubIdTypeDoi]').val();

				var queryString = '';
				if (author) queryString += author.replace(/[()]/g, '');
				if (confName) queryString += ' ' + confName;
				if (source) queryString += ' "' + source + '"';
				if (articleTitle) queryString += ' "' + articleTitle + '"';
				if (doi) queryString += ' ' + doi;
				return queryString;
			{rdelim}
			
			$('#googleQuery').click(function() {ldelim}
				var googleScholarLink = 'http://scholar.google.com/scholar?ie=UTF-8&oe=UTF-8&hl=en&q=' + encodeURIComponent($.trim(createMDSchemaSpecificGSQuery()));
				window.open(googleScholarLink, '_blank');
			{rdelim});

			
			//
			// 4) Author Query
			//
			// Bind action to author query button.
			ajaxAction(
				'post',
				'#authorQueryResult',
				'#authorQuery',
				'{url op="sendAuthorQuery"}',
				null, null,
				'#editCitationForm'
			);

			
			////////////////////////////////////////////////////////////
			// Citation Service Results
			//
			// Handle expert citation service results.
			extrasOnDemand('#citationImprovementResultsBlock');
		
			// Create citation source tabs.
			$('#citationSourceTabs-{$formUid}').tabs();

			/**
			 * Helper function that copies all values in
			 * the given source element list to the
			 * citation field list.
			 * @param $sources jQuery a list of source table cells
			 */
			function copySourceToFieldList($source) {ldelim}
				// Copy all source values to target fields.
				$source.each(function() {ldelim}
					$sourceCell = $(this);
					var propertyName = $sourceCell.attr('id').replace(/^[0-9]+-/, '');
					var $targetField = $('.citation-field[name=' + propertyName + ']');
		
					if ($targetField.length > 0) {ldelim}
						// Copy the content of the source to the target field
						$targetField.val($sourceCell.text());
					{rdelim}
				{rdelim});
				
				// Trigger citation comparison refresh.
				$('#citationFormErrorsAndComparison').triggerHandler('refresh');
			{rdelim}
				
			// Activate "use" buttons.
			$('.citation-source-use-button').die('click').live('click', function() {ldelim}
				// Identify the source element.
				var $source = $(this).closest('td').siblings('.value');
				// Copy values.
				copySourceToFieldList($source);
				// Return false to stop further event processing.
				return false;
			{rdelim});
			
			// Activate "use all" buttons.
			$('.citation-source-use-all-button').die('click').live('click', function() {ldelim}
				// Identify all source elements in this citation source.
				var $source = $(this).closest('tbody').find('.value');
				// Copy values.
				copySourceToFieldList($source);
				// Return false to stop further event processing.
				return false;
			{rdelim});


			////////////////////////////////////////////////////////////
			// Form-level actions.
			//
			//
			// 1) Cancel button.
			//
			$('#citationFormCancel').click(function() {ldelim}
				// Clear the form panel and show the initial help message.
				$('#{$containerId}').replaceWith(emptyEditorText);
	
				// De-select the selected citation.
				$('.current-item').each(function() {ldelim}
					$(this).removeClass('current-item');
				{rdelim});
			{rdelim});

			//
			// 2) Save buttons.
			//
			// Style save buttons.
			{if $citationApproved}
				$('#citationFormSaveAndApprove').hide();
				$('#citationFormSaveAndRevokeApproval').addClass('secondary-button');
			{else}
				$('#citationFormSaveAndRevokeApproval').hide();
				$('#citationFormSave').addClass('secondary-button');
			{/if}
	
			// Handle save button.
			$('.citation-save-button').each(function() {ldelim}
				$(this).click(function() {ldelim}
					var pressedButton = this.id;
					
					// Bind to the form's submitSuccessful custom event which
					// will be called once the citation has been successfully
					// saved.
					$('#{$containerId}').bind('submitSuccessful', function(e, $updatedElement) {ldelim}
						$(this).unbind('submitSuccessful');
						
						// Remove warning about unsaved data.
						$('li.unsaved-data-warning').remove();
						if($('#citationFormMessages .formErrorList').children().length === 0) {ldelim}
							$('#citationFormMessages').remove();
						{rdelim}

						if (pressedButton === 'citationFormSaveAndApprove') {ldelim}
							// Shuffle buttons around.
							$('#citationFormSaveAndApprove').hide();
							$('#citationFormSaveAndRevokeApproval').show();
							$('#citationFormSave').removeClass('secondary-button');

							// Get the next unapproved citation:
							// 1) First try to find an unapproved citation
							//    after the current citation.
							$nextUnapproved = $('#component-grid-citation-citationgrid-row-{$citation->getId()} ~ .unapproved-citation')
								.first();
							// 2) If that wasn't successful then try to find
							//    an unapproved citation from the top of the list.
							if (!$nextUnapproved.length) {ldelim}
								$nextUnapproved = $('.unapproved-citation:not(#component-grid-citation-citationgrid-row-{$citation->getId()})')
									.first();
							{rdelim}

							// If there are still unapproved citations then show
							// the next one, otherwise jump to the export main tab.
							if ($nextUnapproved.length) {ldelim}
								// Scroll the citation list to make the next
								// citation visible.
								scrollToMakeVisible($nextUnapproved);
								
								// Trigger the click handler on the next
								// unapproved citation to load it in the
								// citation detail pane.
								$nextUnapproved.find('.row_file').triggerHandler('click');
							{rdelim} else {ldelim}
								// If all citations have been approved then open
								// the export tab.
								$('.approved-citation .row_file').first().triggerHandler('click');
								$('#citationEditorMainTabs').tabs('select', 'citationEditorTabExport');
							{rdelim}
						{rdelim}
						if (pressedButton === 'citationFormSaveAndRevokeApproval') {ldelim}
							// Shuffle buttons around.
							$('#citationFormSaveAndRevokeApproval').hide();
							$('#citationFormSaveAndApprove').show();
							$('#citationFormSave').addClass('secondary-button');
						{rdelim}

						// If the user pressed only the save button then provide
						// visual feedback.
						if (pressedButton === 'citationFormSave') {ldelim}
							if ($('#citationFormMessages').length == 0) {ldelim}
								var formErrorHtml =
									'<div id="citationFormMessages" title="{translate key="submission.citations.editor.details.clickToDismissMessage"}" class="help-message">'+
									'    <div id="formErrors">'+
									'        <p><span class="formError">{translate key="submission.citations.editor.details.messages"}:</span></p>'+
									'        <ul class="formErrorList"></ul>'+
									'    </div>'+
									'</div>';
								$('#citationFormErrorsAndComparison').prepend(formErrorHtml);
							{rdelim}
							var messageHtml = '<li class="unsaved-data-warning">{translate key="submission.citations.editor.details.dataSaved"}</li>';
							$('#formErrors .formErrorList').append(messageHtml);
						{rdelim}
						
						
						{if !$citation->getId()}
							// A new citation has been saved so refresh the form to get
							// the new citation id.
							$updatedElement.find('.row_file').triggerHandler('click');
						{/if}
					{rdelim});

					// Fill hidden form elements depending on the save action type.
					if (pressedButton === 'citationFormSaveAndApprove') {ldelim}
						$('#citationApproved').val('citationApproved');
						$('#remainsCurrentItem').val('no');
						
						// Throbber because we'll move to a new citation or tab.
						actionThrobber('#{$containerId}');
					{rdelim} else {ldelim}
						$('#remainsCurrentItem').val('yes');
					{rdelim}
					if (pressedButton === 'citationFormSaveAndRevokeApproval') {ldelim}
						$('#citationApproved').val('');
					{rdelim}
					
					// Submit the form.
					{if $citation->getId()}
						// Update existing citation.
						submitJsonForm('#{$containerId}', 'replace', '#component-grid-citation-citationgrid-row-{$citation->getId()}');
					{else}
						// Create and the new citation.
						submitJsonForm('#{$containerId}', 'append', '#component-grid-citation-citationgrid tbody:first');
					{/if}

					// Trigger the throbber for citation approval or when we
					// add a new citation as this will change the whole citation
					// detail pane.
					{if $citation->getId()}if (pressedButton === 'citationFormSaveAndApprove') {ldelim}{/if}
						$('#{$containerId}').triggerHandler('actionStart');
					{if $citation->getId()}{rdelim}{/if}
				{rdelim});
			{rdelim});
	
			// Handle highlighting of currently edited citation.
			$('#citationEditorNavPane div.grid .current-item').removeClass('current-item');
			$('#component-grid-citation-citationgrid-row-{$citation->getId()}').addClass('current-item');
	
			// Throbber
			actionThrobber('#{$containerId}');
		{rdelim});
	</script>
	<form name="editCitationForm" id="editCitationForm" method="post" action="{url op="updateCitation"}" >
		<div class="wrapper scrollable with-pane-actions">
			{include file="controllers/grid/citation/form/citationFormErrorsAndComparison.tpl"}

			{* Only show the citation improvement block when editing an existin citation *}
			{if $citation->getId()}
				<div id="citationImprovementBlock"> 
					<p>{translate key="submission.citations.editor.details.explainImprovementOptions"}</p>
				
					<div id="citationImprovement">
						<ul>
							<li><a href="#citationImprovementManual">Manual Editing</a></li>
							<li><a href="#citationImprovementQuery">Citation Services</a></li>
							<li><a href="#citationImprovementGoogle">Google Scholar</a></li>
							<li><a href="#citationImprovementAuthor">Ask Author</a></li>
						</ul>
						
						<div id="citationImprovementManual" class="grid">
							<table>
								{* Create initial field list which will then be maintained via JS. *}
								{foreach from=$availableFields name=availableFields key=fieldName item=field}
									{capture assign=fieldValueVar}{ldelim}${$fieldName}{rdelim}{/capture}
									{eval|assign:"fieldValue" var=$fieldValueVar}
									{if $fieldValue != ''}
										{if $field.required == 'true'}{assign var=hasRequiredField value=true}{/if}
										{include file="controllers/grid/citation/form/citationInputField.tpl" availableFields=$availableFields fieldName=$fieldName fieldValue=$fieldValue required=$field.required}
									{/if}
								{/foreach}
							</table>
							
							{if $hasRequiredField}<p><span class="formRequired">{translate key="common.requiredField"}</span></p>{/if}
						</div>
						
						<div id="citationImprovementQuery">
							<div>
								<p>{translate key="submission.citations.editor.details.databaseQueryExplanation"}</p>
								{include file="controllers/grid/citation/form/citationFilterOptionBlock.tpl"
									titleKey="submission.citations.editor.details.editRawCitationDatabaseServices"
									availableFilters=$availableLookupFilters}
							</div>
							<div class="actions">
								<button id="queryCitation" type="button">{translate key="submission.citations.editor.details.queryCitation"}</button>
							</div>
							<div class="clear"></div>
						</div>
						
						<div id="citationImprovementGoogle">
							<div>
								<p>{translate key="submission.citations.editor.details.googleScholarExplanation"}</p>
							</div>
							<div class="actions">
								<button id="googleQuery" type="button">{translate key="submission.citations.editor.details.queryGoogleScholar"}</button>
							</div>
							<div class="clear"></div>
						</div>
						
						<div id="citationImprovementAuthor">
							<div>
								<p>{translate key="submission.citations.editor.details.authorQueryExplanation"}</p>
								<p>
									{translate key="submission.citations.editor.details.authorQuerySubject"}
									<input type="text" maxlength="500" value="{$authorQuerySubject}"
										id="authorQuerySubject" name="authorQuerySubject" validation="required" /> 
								</p>
								<p>
									{translate key="submission.citations.editor.details.authorQueryBody"}
									<textarea class="textarea" validation="required" rows=10
										id="authorQueryBody" name="authorQueryBody">{$authorQueryBody}</textarea>
								</p>
							</div>
							<div id="authorQueryResult"></div>
							<div class="actions">
								<button id="authorQuery" type="button">{translate key="submission.citations.editor.details.sendAuthorQuery"}</button>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
	
				<div id="citationImprovementResultsBlock">
					<div class="options-head">
						<span class="ui-icon"></span>
						<span class="option-block-inactive">{translate key="submission.citations.editor.details.citationImprovementResultsInactive"}</span>
						<span class="option-block-active">{translate key="submission.citations.editor.details.citationImprovementResultsActive"}</span>
					</div>
					<div class="option-block">
						{* Tabs that contain source data *}
						<div id="citationSourceTabs-{$formUid}">
							{* Tab definition *}
							<ul>
								{foreach from=$citationSourceTabs key=citationSourceTabId item=citationSourceTab}
									<li><a href="#{$citationSourceTabId}-{$formUid}">{$citationSourceTab.displayName|escape}</a></li>
								{/foreach}
							</ul>
							
							{* Tab content *}
							{foreach from=$citationSourceTabs key=citationSourceTabId item=citationSourceTab}
								<div id="{$citationSourceTabId}-{$formUid}" class="grid">
									<table><tbody>
										{foreach from=$citationSourceTab.statements key=sourcePropertyId item=sourceStatement}
											<tr valign="top">
												<td width="30%" class="label">{translate key=$sourceStatement.displayName}</td>
												<td id="{$sourcePropertyId}" class="value">{$sourceStatement.value|escape}</td>
												<td class="citation-source-action-cell">
													[<a id="{$sourcePropertyId}-use" href="" class="citation-source-use-button" title="{translate key="submission.citations.editor.details.sourceResultsUseExplanation"}">{translate key="submission.citations.editor.details.sourceResultsUse"}</a>]
												</td>
											</tr>
										{/foreach}
										<tr class="citation-source-action-row">
											<td></td>
											<td></td>
											<td class="citation-source-action-cell">
												<button id="{$citationSourceTabId}-{$formUid}-use-all" type="button" class="citation-source-use-all-button" title="{translate key="submission.citations.editor.details.sourceResultsUseAllExplanation"}">{translate key="submission.citations.editor.details.sourceResultsUseAll"}</button>
											</td>
										</tr>
									</tbody></table>
								</div>
							{/foreach}
						</div>
					</div>				
				</div>
				<input type="hidden" name="citationId" value="{$citation->getId()|escape}" />
				<input type="hidden" name="citationState" value="{$citation->getCitationState()|escape}" />
			{/if}
		</div>
			
		<input type="hidden" name="assocId" value="{$citation->getAssocId()|escape}" />
		<input id="citationApproved" type="hidden" name="citationApproved" value="{if $citationApproved}citationApproved{/if}" />
		<input id="remainsCurrentItem" type="hidden" name="remainsCurrentItem" value="yes" />
		
		<div class="pane-actions form-block">
			<div>
				<button id="citationFormSaveAndRevokeApproval" type="button" class="citation-save-button secondary-button">{translate key="submission.citations.editor.details.saveAndRevokeApproval"}</button>
				<button id="citationFormSave" type="button" class="citation-save-button">{if $citation->getId()}{translate key="common.save"}{else}{translate key="common.add"}{/if}</button>
				<button id="citationFormSaveAndApprove" type="button" class="citation-save-button">{if $citation->getId()}{translate key="submission.citations.editor.details.saveAndApprove"}{else}{translate key="submission.citations.editor.details.addAndApprove"}{/if}</button>
				<button id="citationFormCancel" type="button">{translate key="common.cancel"}</button>
			</div>
		</div>
	</form>
</div>
