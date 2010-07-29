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
					<div class="wrapper">
						<div class="help-message">{translate|escape key="submission.citations.editor.details.pleaseClickOnCitationToStartEditing"}</div>
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
			// Create citation improvement tabs.
			$('#citationImprovement').tabs();

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
					// Trigger a change event on the citation
					// comparison container to refresh it.
					$('#citationFormErrorsAndComparison').triggerHandler('refresh');
				{rdelim});
				return false;
			{rdelim});
		
			//
			// 2) Citation Services (Query)
			//
			// FIXME

			//
			// 3) Citation Services (Results)
			//
			// Create citation source tabs.
			$('#citationSourceTabs-{$formUid}').tabs();

			//
			// 4) Google Scholar
			//
			// FIXME

			//
			// 5) Author Query
			//
			// FIXME

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

							// Get the next unapproved citation.
							$nextUnapproved = $('.unapproved-citation:not(#component-grid-citation-citationgrid-row-{$citation->getId()}) .row_file')
									.first();
							if ($nextUnapproved.length) {ldelim}
								// If there still are unapproved citations then
								// load the next one.
								$nextUnapproved.triggerHandler('click');
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
						// Create new citation.
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
		<div class="wrapper scrollable">
			{include file="controllers/grid/citation/form/citationFormErrorsAndComparison.tpl"}

			<div id="citationImprovementBlock" class="form-block"> 
				<p>{translate key="submission.citations.editor.details.explainImprovementOptions"}</p>
			
				<div id="citationImprovement">
					<ul>
						<li><a href="#citationImprovementManual">Manual Editing</a></li>
						<li><a href="#citationImprovementQuery">Citation Services</a></li>
						<li><a href="#citationImprovementResults">Citation Services</a></li>
						<li><a href="#citationImprovementGoogle">Google Scholar</a></li>
						<li><a href="#citationImprovementAuthor">Ask Author</a></li>
					</ul>
					<div id="citationImprovementManual" class="form-block grid">
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
					<div id="citationImprovementQuery" class="form-block grid">
						External Citation Services
		
						<div class="form-block">
							{* include file="linkAction/linkAction.tpl" action=$checkAction id=$containerId *}
							{* <script type="text/javascript">
								// FIXME: Move this to the templates generas JS once we've fully implemented
								// DB queries.
								$(function() {ldelim}
									// Throbber when citation is re-checked.
									$('#{$containerId}-{$checkAction->getId()}-button').click(function() {ldelim}
										actionThrobber('#{$containerId}');
									{rdelim});
								{rdelim});
							</script> *}
						</div>
					</div>
					
					<div id="citationImprovementResults" class="form-block grid">
						{* Tabs that contain source data *}
						<div id="citationSourceTabs-{$formUid}" class="form-block">
							{* Tab definition *}
							<ul>
								{foreach from=$citationSourceTabs key=citationSourceTabId item=citationSourceTab}
									<li><a href="#{$citationSourceTabId}-{$formUid}">{$citationSourceTab.displayName|escape}</a></li>
								{/foreach}
							</ul>
							
							{* Tab content *}
							{foreach from=$citationSourceTabs key=citationSourceTabId item=citationSourceTab}
								<div id="{$citationSourceTabId}-{$formUid}">
									<table>
										{foreach from=$citationSourceTab.statements key=sourcePropertyId item=sourceStatement}
											<tr valign="top">
												<td width="30%" class="label">{translate key=$sourceStatement.displayName}</td>
												<td width="65%" id="{$sourcePropertyId}" class="value">{$sourceStatement.value|escape}</td>
												<td width="5%">
													<a id="{$sourcePropertyId}-use" href="">use</a>
													<script type='text/javascript'>
														$(function() {ldelim}
															$('#{$sourcePropertyId}-use').click(function() {ldelim}
																// Identify the source and target elements.
																var $source = $('#{$citationSourceTabId}-{$formUid} #{$sourcePropertyId}');
																var $target = $('.citation-field[name={$sourcePropertyId|regex_replace:'/^[0-9]+-/':''}]');

																if ($target.length > 0) {ldelim}
																	// Copy the content of the source to the target field
																	$target.val($source.text());
																{rdelim}
																return false;
															{rdelim});
														{rdelim});
													</script>
												</td>
											</tr>
										{/foreach}
									</table>
								</div>
							{/foreach}
						</div>
					</div>
					
					<div id="citationImprovementGoogle" class="form-block grid">
						Google Scholar
		
						<div class="form-block">
							<a href="http://scholar.google.com/scholar?ie=UTF-8&oe=UTF-8&hl=en&q={if $citationFormTabs.Filled.nlm30PersonGroupPersonGroupTypeAuthor}author:%22{$nlm30PersonGroupPersonGroupTypeAuthor|escape:'url'}%22+{/if}%22{if $nlm30ConfName}{$nlm30ConfName|escape:'url'}{else}{$nlm30Source|escape:'url'}{/if}%22+{$nlm30ArticleTitle|escape:'url'}{if $nlm30PubIdPubIdTypeDoi}+{$nlm30PubIdPubIdTypeDoi|escape:'url'}{/if}" target="_blank">{translate key="submission.citations.editor.details.checkInGoogleScholar"}</a>
						</div>
					</div>
					
					<div id="citationImprovementAuthor" class="form-block grid">
						Ask Author
					</div>
				</div>
			</div>
		</div>
			
		<input type="hidden" name="assocId" value="{$citation->getAssocId()|escape}" />
		<input id="citationApproved" type="hidden" name="citationApproved" value="{if $citationApproved}citationApproved{/if}" />
		<input id="remainsCurrentItem" type="hidden" name="remainsCurrentItem" value="yes" />
		{if $citation->getId()}
			<input type="hidden" name="citationId" value="{$citation->getId()|escape}" />
			<input type="hidden" name="citationState" value="{$citation->getCitationState()|escape}" />
		{/if}
		
		<div class="pane_actions form-block"><div>
			<button id="citationFormSaveAndRevokeApproval" type="button" class="citation-save-button secondary-button">{translate key="submission.citations.editor.details.saveAndRevokeApproval"}</button>
			<button id="citationFormSave" type="button" class="citation-save-button">{if $citation->getId()}{translate key="common.save"}{else}{translate key="common.add"}{/if}</button>
			<button id="citationFormSaveAndApprove" type="button" class="citation-save-button">{if $citation->getId()}{translate key="submission.citations.editor.details.saveAndApprove"}{else}{translate key="submission.citations.editor.details.addAndApprove"}{/if}</button>
			<button id="citationFormCancel" type="button">{translate key="common.cancel"}</button>
		</div></div>
	</form>
</div>
