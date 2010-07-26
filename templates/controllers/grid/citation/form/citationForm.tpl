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
			// Create text to be inserted into the empty editor pane.
			emptyEditorText = '{strip}
				<div id="{$containerId}" class="canvas">
					<div class="wrapper">
						<div class="help-message">{translate key="submission.citations.pleaseClickOnCitationToStartEditing"}</div>
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

			// Represent citation fields as an object in JS.
			{assign var=hasRequiredField value=false}
			citationFields = {ldelim}
				{foreach from=$availableFields key=fieldName item=field}
					{capture assign=fieldValueVar}{ldelim}${$fieldName}{rdelim}{/capture}
					{eval|assign:"fieldValue" var=$fieldValueVar}
					'{$fieldName}': ['{translate|escape:javascript key=$field.displayName}', {$field.required}, '{$fieldValue|escape:javascript}'],
				{/foreach}
			{rdelim};

			// Create citation source tabs.
			$('#citationSourceTabs-{$formUid}').tabs();

			// Create citation improvement tabs.
			$('#citationImprovement').tabs();
			
			// Handle cancel button.
			$('#citationFormCancel').click(function() {ldelim}
				// Clear the form panel and show the initial help message.
				$('#{$containerId}').replaceWith(emptyEditorText);
	
				// De-select the selected citation.
				$('.current-item').each(function() {ldelim}
					$(this).removeClass('current-item');
				{rdelim});
			{rdelim});

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
					pressedButton = this.id;
					
					// Bind to the form's submitSuccessful custom event which
					// will be called once the citation has been successfully
					// saved.
					$('#{$containerId}').bind('submitSuccessful', function(e, $updatedElement) {ldelim}
						$(this).unbind('submitSuccessful');
						
						// Remove warning about unsaved data.
						$('p.unsaved-data-warning').remove();
						if($('#citationFormMessages').children().length === 0) {ldelim}
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
						submitJsonForm('#{$containerId}', 'append', '#component-grid-citation-citationgrid tbody:not(.empty)');
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
				<p>{translate key="submission.citations.form.description"}</p>
			
				<div id="citationImprovement">
					<ul>
						<li><a href="#citationImprovementManual">Manual Editing</a></li>
						<li><a href="#citationImprovementDatabase">Database Query</a></li>
						<li><a href="#citationImprovementGoogle">Google Scholar</a></li>
						<li><a href="#citationImprovementAuthor">Ask Author</a></li>
						<li><a href="#citationImprovementSources">View Sources</a></li>
					</ul>
					<div id="citationImprovementManual" class="form-block grid">
						<table>
							{* Create initial field list which will then be maintained via JS. *}
							{foreach from=$availableFields key=fieldName item=field}
								{capture assign=fieldValueVar}{ldelim}${$fieldName}{rdelim}{/capture}
								{eval|assign:"fieldValue" var=$fieldValueVar}
								{if $fieldValue != ''}
									{if $field.required == 'true'}{assign var=hasRequiredField value=true}{/if}
									<tr id="{$fieldName}"{if $field.required == 'true'} class="citation-field-required"{/if}>
										<td class="first_column" width="30%">
											<div class="row_container">
												<div class="row_file label">{translate|escape key=$field.displayName}</div>
												<div class="row_actions">
													<a class="delete" title="{translate key="common.delete"}" href=""></a>
												</div>
											</div>
										</td>
										<td class="value">
											<input type="text" class="citation-field text large" value="{$fieldValue|escape}" id="{$fieldName}" name="{$fieldName}">
										</td>
									</tr>
								{/if}
							{/foreach}
						</table>
						
						{if $hasRequiredField}<p><span class="formRequired">{translate key="common.requiredField"}</span></p>{/if}
					</div>
					<div id="citationImprovementDatabase" class="form-block grid">
						Databases
		
						<div class="form-block">
							{include file="linkAction/linkAction.tpl" action=$checkAction id=$containerId}
							<script type="text/javascript">
								// FIXME: Move this to the templates generas JS once we've fully implemented
								// DB queries.
								$(function() {ldelim}
									// Throbber when citation is re-checked.
									$('#{$containerId}-{$checkAction->getId()}-button').click(function() {ldelim}
										actionThrobber('#{$containerId}');
									{rdelim});
								{rdelim});
							</script>
						</div>
					</div>
					
					<div id="citationImprovementGoogle" class="form-block grid">
						Google Scholar
		
						<div class="form-block">
							<a href="http://scholar.google.com/scholar?ie=UTF-8&oe=UTF-8&hl=en&q={if $citationFormTabs.Filled.nlm30PersonGroupPersonGroupTypeAuthor}author:%22{$nlm30PersonGroupPersonGroupTypeAuthor|escape:'url'}%22+{/if}%22{if $nlm30ConfName}{$nlm30ConfName|escape:'url'}{else}{$nlm30Source|escape:'url'}{/if}%22+{$nlm30ArticleTitle|escape:'url'}{if $nlm30PubIdPubIdTypeDoi}+{$nlm30PubIdPubIdTypeDoi|escape:'url'}{/if}" target="_blank">{translate key="submission.citations.grid.checkGoogleScholar"}</a>
						</div>
					</div>
					
					<div id="citationImprovementAuthor" class="form-block grid">
						Ask Author
					</div>
					
					<div id="citationImprovementSources" class="form-block grid">
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
													{literal}<script type='text/javascript'>
														$(function() {
															$('#{/literal}{$sourcePropertyId}{literal}-use').click(function() {
																// Create the source and target selector
																tabId = '{/literal}{$formUid}{literal}';
																sourcePropertyId = '{/literal}{$sourcePropertyId}{literal}';
																sourceTabId = sourcePropertyId.substring(0, sourcePropertyId.indexOf('-'));
																sourceSelector = '#' + sourceTabId + '-' + tabId + ' #' + sourcePropertyId;
																targetFieldId = sourcePropertyId.substring(sourcePropertyId.indexOf('-') + 1);
																targetSelector = '#Filled-' + tabId + ' #' + targetFieldId + ', #Empty-' + tabId + ' #' + targetFieldId
																
																// Copy the content of the source to the target field
																$(targetSelector).val($(sourceSelector).text());
																return false;
															});
														});
													</script>{/literal}
												</td>
											</tr>
										{/foreach}
									</table>
								</div>
							{/foreach}
						</div>
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
			<button id="citationFormSaveAndRevokeApproval" type="button" class="citation-save-button secondary-button">{translate key="submission.citations.saveAndRevokeApproval"}</button>
			<button id="citationFormSave" type="button" class="citation-save-button">{if $citation->getId()}{translate key="common.save"}{else}{translate key="common.add"}{/if}</button>
			<button id="citationFormSaveAndApprove" type="button" class="citation-save-button">{if $citation->getId()}{translate key="submission.citations.saveAndApprove"}{else}{translate key="submission.citations.addAndApprove"}{/if}</button>
			<button id="citationFormCancel" type="button">{translate key="common.cancel"}</button>
		</div></div>
	</form>
</div>
