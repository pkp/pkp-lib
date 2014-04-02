{**
 * templates/controllers/grid/citation/form/citationForm.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Citation grid form
 *}

{assign var=containerId value="citationEditorDetailCanvas"}
{assign var=formUid value="form"|uniqid}
<div id="{$containerId}" class="canvas">
	<script type="text/javascript">
		<!--
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

			// Add keyboard shortcuts.
			$(document)
				.unbind('keydown').unbind('keypress')

				// Ctrl-R: save and revoke approval
				.bind('keydown', 'ctrl+r', function() {ldelim}
					$('#citationFormSaveAndRevokeApproval').click();
					return false;
				{rdelim})

				// Ctrl-S: save
				.bind('keydown', 'ctrl+s', function() {ldelim}
					$('#citationFormSave').click();
					return false;
				{rdelim})

				// Ctrl-Enter: save and approve
				.bind('keydown', 'ctrl+return', function() {ldelim}
					$('#citationFormSaveAndApprove').click();
					return false;
				{rdelim})

				// Ctrl-M: manual editing
				.bind('keydown', 'ctrl+m', function() {ldelim}
					$('#citationImprovement').tabs('select', 0);
					$('.citation-field').first().focus();
					return false;
				{rdelim})

				// Ctrl-D: citation services (databases)
				.bind('keydown', 'ctrl+d', function() {ldelim}
					$('#citationImprovement').tabs('select', 1);
					return false;
				{rdelim})

				// Ctrl-G: Google Scholar
				.bind('keydown', 'ctrl+g', function() {ldelim}
					$('#citationImprovement').tabs('select', 2);
					return false;
				{rdelim})

				// Ctrl-Q: author query
				.bind('keydown', 'ctrl+q', function() {ldelim}
					$('#citationImprovement').tabs('select', 3);
					return false;
				{rdelim})

				// Ctrl-H: show sources
				.bind('keydown', 'ctrl+h', function() {ldelim}
					$('#citationImprovementResultsBlock>.options-head').click();
					return false;
				{rdelim})

				// Esc: cancel
				.bind('keydown', 'esc', function() {ldelim}
					$('#citationFormCancel').click();
					return false;
				{rdelim})

				// Additionally bind to "keypress" event to cancel the default event in Opera.
				.bind('keypress', 'ctrl+h ctrl+r ctrl+s ctrl+return ctrl+m ctrl+d ctrl+g ctrl+q esc', function() {ldelim}
					return false;
				{rdelim});



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

			// Autocomplete feature.
			/**
			 * Add autocomplete to the given field.
			 * @param fieldName string
			 */
			var addAutocomplete = function(fieldName) {ldelim}
				// Set up local options database.
				var autocompleteOptions = {ldelim}{strip}
					{assign var=firstOption value=true}
					{foreach from=$availableFields key=fieldName item=field}
						{if $field.options}
							{if !$firstOption}, {/if}
							'{$fieldName}': [
								{foreach name=options from=$field.options item=option}
									'{$option|escape:javascript}'{if !$smarty.foreach.options.last},{/if}
								{/foreach}
							]
							{assign var=firstOption value=false}
						{/if}
					{/foreach}
				{/strip}{rdelim};

				// jQuerify the field.
				$citationField = $('.citation-field[name="' + fieldName + '"]');

				// Remove any previous autocomplete that may currently
				// be attached to the given field.
				$citationField.autocomplete('destroy');

				// If the given field has options then add
				// a new autocomplete.
				if (autocompleteOptions[fieldName] !== undefined) {ldelim}
					$citationField.autocomplete({ldelim}
						source: autocompleteOptions[fieldName],
						minLength: 0,
						focus: function(event, ui) {ldelim}
							$(this).val(ui.item.label);
							return false;
						{rdelim},
						select: function (event, ui) {ldelim}
							$(this).val(ui.item.value);
							return false;
						{rdelim}
					{rdelim});

					// Fixing autocomplete font size (the autocomplete
					// markup is appended to the end of the document and
					// therefore doesn't correctly inherit styles).
					$citationField
						.autocomplete('widget')
						.css('font-size', '55%');
				{rdelim}
			{rdelim};

			// Add initial auto-complete info to fields.
			$('.citation-field').each(function() {ldelim}
				addAutocomplete($(this).attr('name'));
			{rdelim});

			/**
			 * Function that handles label change.
			 * @param $label jQuery
			 * @param refresh boolean whether to refresh
			 *  the citation comparison after the change.
			 */
			var labelChangeHandler = function($label, refresh) {ldelim}
				var newName = $label.val();
				var originalName = $label.data('original-value');

				// Filter fake change events in IE.
				if (newName === originalName) return false;

				// Don't allow unsetting the label.
				if (newName === '-1') {ldelim}
					alert('{translate|escape:javascript key="submission.citations.editor.details.cannotSelectDefaultForLabel"}');
					$label.val(originalName);
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
					$this.closest('tr').find('select')
						.val(-1).data('original-value', -1)
						.css('color', '#990000')
						.children().css('color', '#222222');
				{rdelim});

				// Reset the color of the label in
				// case we had marked it before.
				$label.css('color', '#222222');

				// Find the corresponding input field and
				// set its name attribute.
				$label.closest('tr').find('input')
					// Set the name to the chosen field name.
					.attr('name', newName)
					// Remove the "new-citation-field" class
					// in case this was a new field
					.removeClass('new-citation-field');

				// Store the new value for future reference.
				$label.data('original-value', newName);

				// Add auto-complete data (if any).
				addAutocomplete(newName);

				if (refresh) {ldelim}
					// Trigger a change event so that the citation
					// comparison is being updated.
					$('#citationFormErrorsAndComparison').triggerHandler('refresh');
				{rdelim}
			{rdelim};

			// Bind initial change handlers for label change.
			// NB: We cannot use live() here as live for change is broken on IE.
			$('.citation-field-label').change(function() {ldelim}
				labelChangeHandler($(this), true);
			{rdelim});


			// Handle addition of new fields:
			// - Define helper function
			/**
			 * Add a new field for manual editing.
			 */
			var addNewCitationField = function() {ldelim}
				{capture assign=htmlForNewField}{include file="controllers/grid/citation/form/citationInputField.tpl" availableFields=$availableFields fieldName="new"}{/capture}
				var htmlForNewField = '{$htmlForNewField|escape:javascript}';
				var $newField = $('#citationImprovementManual tbody')
					.append(htmlForNewField).children().last();
				// Hide the label drop-down and the delete action
				// until the user enters a value
				$newField.find('a, select').hide();

				// Configure drop-down.
				$newField.find('select')
					// Set the original value.
					.data('original-value', '-1')
					// Bind change handler for label change.
					// NB: We cannot use live() here as live for change is broken on IE.
					.change(function() {ldelim}
						labelChangeHandler($(this), true);
					{rdelim});
			{rdelim};

			// - Append the a first new input field to
			//   the field list.
			addNewCitationField();

			// - Remove help text and show field label
			//   selector on focus of the new field.
			/**
			 * Activate the waiting empty citation field for
			 * editing.
			 * @param $newField jQuery
			 */
			var activateNewCitationField = function($newField) {ldelim}
				if ($newField.val() === '{translate|escape:javascript key="submission.citations.editor.details.newFieldInfo"}') {ldelim}
					$newField
						// Empty the field.
						.val('')
						// Show label selector and delete button.
						.closest('tr').find('a, select').fadeIn(500);

					// Add new empty field to be edited next.
					addNewCitationField();
				{rdelim}
			{rdelim};
			$('.new-citation-field').die('focus').live('focus', function() {ldelim}
				activateNewCitationField($(this));
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
			 * Return a Google Scholar query string
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
			var createMDSchemaSpecificGSQuery = function() {ldelim}
				var author = $('input[name=nlm30PersonGroupPersonGroupTypeAuthor]').val();
				var confName = $('input[name=nlm30ConfName]').val();
				var source = $('input[name=nlm30Source]').val();
				var articleTitle = $('input[name=nlm30ArticleTitle]').val();
				var doi = $('input[name=nlm30PubIdPubIdTypeDoi]').val();

				var queryString = '';
				if (author) queryString += author.replace(/[()]/g, '');
				if (confName) queryString += ' ' + confName;
				if (source) queryString += ' ' + source;
				if (articleTitle) queryString += ' ' + articleTitle;
				if (doi) queryString += ' ' + doi;
				return queryString;
			{rdelim};

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
			var copySourceToFieldList = function($source) {ldelim}
				// Copy all source values to target fields.
				$source.each(function() {ldelim}
					$sourceCell = $(this);
					var propertyName = $sourceCell.attr('id').replace(/^[0-9]+-/, '');
					var $targetField = $('.citation-field[name=' + propertyName + ']');

					if ($targetField.length === 0) {ldelim}
						// The target field does not exist yet. So let's
						// configure a new field.
						$targetField = $('.new-citation-field');
						activateNewCitationField($targetField);

						// Set the correct label for the new field.
						$label = $targetField.closest('tr').find('select');
						$label.val(propertyName);
						labelChangeHandler($label, false);
					{rdelim}

					// Copy the content of the source to the target field
					$targetField.val($sourceCell.text());
				{rdelim});

				// Trigger citation comparison refresh.
				$('#citationFormErrorsAndComparison').triggerHandler('refresh');
			{rdelim};

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
			// 2) Save/Add (+Revoke/Approve) buttons.
			//
			// Style save buttons.
			{if $citationApproved}
				$('#citationFormSaveAndApprove').hide();
				$('#citationFormSaveAndRevokeApproval').addClass('secondary-button');
			{else}
				$('#citationFormSaveAndRevokeApproval').hide();
				{if $citation->getId()}
					$('#citationFormSave').addClass('secondary-button');
				{else}
					$('#citationFormCancel').addClass('secondary-button');
				{/if}
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
						if($('#citationFormMessages .pkp_form_error_list').children().length === 0) {ldelim}
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
									'    <div id="pkp_form_errors">'+
									'        <p><span class="pkp_form_error">{translate key="submission.citations.editor.details.messages"}:</span></p>'+
									'        <ul class="pkp_form_error_list"></ul>'+
									'    </div>'+
									'</div>';
								$('#citationFormErrorsAndComparison').prepend(formErrorHtml);
							{rdelim}
							var messageHtml = '<li class="unsaved-data-warning">{translate key="submission.citations.editor.details.dataSaved"}</li>';
							$('#formErrors .pkp_form_error_list').append(messageHtml);
						{rdelim}


						{if !$citation->getId()}
							// A new citation has been saved so refresh the form to get
							// the new citation id.
							scrollToMakeVisible($updatedElement);
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
						submitJsonForm('#{$containerId}', 'append', '#component-grid-citation-citationgrid .scrollable tbody:first');
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
		// -->
	</script>
	<form class="pkp_form" id="editCitationForm" method="post" action="{url op="updateCitation"}" >
		<div class="wrapper scrollable with-pane-actions">
			{include file="controllers/grid/citation/form/citationFormErrorsAndComparison.tpl"}

			{* Only show the citation improvement block when editing an existin citation *}
			{if $citation->getId()}
				<div id="citationImprovementBlock">
					<p>{translate key="submission.citations.editor.details.explainImprovementOptions"}</p>

					<div id="citationImprovement">
						<ul>
							<li><a href="#citationImprovementManual" title="{translate key="submission.citations.editor.details.manualEditing"} [Ctrl-M]">{translate key="submission.citations.editor.details.manualEditing"}</a></li>
							<li><a href="#citationImprovementQuery" title="{translate key="submission.citations.editor.details.citationServices"} [Ctrl-D]">{translate key="submission.citations.editor.details.citationServices"}</a></li>
							<li><a href="#citationImprovementGoogle" title="{translate key="submission.citations.editor.details.googleScholar"} [Ctrl-G]">{translate key="submission.citations.editor.details.googleScholar"}</a></li>
							<li><a href="#citationImprovementAuthor" title="{translate key="submission.citations.editor.details.authorQuery"} [Ctrl-Q]">{translate key="submission.citations.editor.details.authorQuery"}</a></li>
						</ul>

						<div id="citationImprovementManual" class="pkp_controllers_grid">
							<table><tbody>
								{* Create initial field list which will then be maintained via JS. *}
								{foreach from=$availableFields name=availableFields key=fieldName item=field}
									{capture assign=fieldValueVar}{ldelim}${$fieldName}{rdelim}{/capture}
									{eval|assign:"fieldValue" var=$fieldValueVar}
									{if $fieldValue != ''}
										{if $field.required == 'true'}{assign var=hasRequiredField value=true}{/if}
										{include file="controllers/grid/citation/form/citationInputField.tpl"
											availableFields=$availableFields
											fieldName=$fieldName
											fieldValue=$fieldValue
											required=$field.required}
									{/if}
								{/foreach}
							</tbody></table>

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
							<div class="pkp_helpers_clear"></div>
						</div>

						<div id="citationImprovementGoogle">
							<div>
								<p>{translate key="submission.citations.editor.details.googleScholarExplanation"}</p>
							</div>
							<div class="actions">
								<button id="googleQuery" type="button">{translate key="submission.citations.editor.details.queryGoogleScholar"}</button>
							</div>
							<div class="pkp_helpers_clear"></div>
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
							<div class="pkp_helpers_clear"></div>
						</div>
					</div>
				</div>

				<div id="citationImprovementResultsBlock">
					<div class="options-head">
						<span class="ui-icon"></span>
						<span class="option-block-inactive" title="{translate key="submission.citations.editor.details.citationImprovementResultsInactive"} [Ctrl-H]">{translate key="submission.citations.editor.details.citationImprovementResultsInactive"}</span>
						<span class="option-block-active" title="{translate key="submission.citations.editor.details.citationImprovementResultsActive"} [Ctrl-H]">{translate key="submission.citations.editor.details.citationImprovementResultsActive"}</span>
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
				<button id="citationFormSaveAndRevokeApproval" type="button" class="citation-save-button secondary-button" title="{translate key="submission.citations.editor.details.saveAndRevokeApproval"} [Ctrl-R]">{translate key="submission.citations.editor.details.saveAndRevokeApproval"}</button>
				<button id="citationFormSave" type="button" class="citation-save-button" title="{if $citation->getId()}{translate key="common.save"}{else}{translate key="common.add"}{/if} [Ctrl-S]">{if $citation->getId()}{translate key="common.save"}{else}{translate key="common.add"}{/if}</button>
				{if $citation->getId()}<button id="citationFormSaveAndApprove" type="button" class="citation-save-button" title="{translate key="submission.citations.editor.details.saveAndApprove"} [Ctrl-Enter]">{translate key="submission.citations.editor.details.saveAndApprove"}</button>{/if}
				<button id="citationFormCancel" type="button" title="{translate key="common.cancel"} [Esc]">{translate key="common.cancel"}</button>
			</div>
		</div>
	</form>
</div>

