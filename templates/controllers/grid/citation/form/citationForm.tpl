{**
 * citationForm.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Citation grid form
 *}

{assign var=containerId value="editCitationFormContainer-"|uniqid}
<div id="citationEditorDetailCanvas" class="canvas">
	{assign var=tabUid value="tab"|uniqid}
	<script type="text/javascript">
		$(function() {ldelim}
			// Create tabs.
			$("#citationFormTab-{$tabUid}").tabs();
			
			// Create text to be inserted into the empty editor pane.
			emptyEditorText = '{strip}
				<div id="citationEditorDetailCanvas" class="canvas">
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
					$('#citationEditorDetailCanvas').replaceWith(emptyEditorText);
				{rdelim});
			{/if}
	
			// Handle cancel button.
			$('#citationFormCancel').click(function() {ldelim}
				// Clear the form panel and show the initial help message.
				$('#citationEditorDetailCanvas').replaceWith(emptyEditorText);
	
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
					
					// Bind to the form's submitSuccessful custom event.
					$('#citationEditorDetailCanvas').bind('submitSuccessful', function() {ldelim}
						$(this).unbind('submitSuccessful');
						
						// Remove warning about unsaved data.
						$('p.unsaved-data-warning').remove();

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
					{rdelim});

					// Fill hidden form elements depending on the save action type.
					if (pressedButton === 'citationFormSaveAndApprove') {ldelim}
						$('#citationApproved').val('citationApproved');
						$('#remainsCurrentItem').val('no');
						
						// Throbber because we'll move to a new citation or tab.
						actionThrobber('#citationEditorDetailCanvas');
					{rdelim} else {ldelim}
						$('#remainsCurrentItem').val('yes');
					{rdelim}
					if (pressedButton === 'citationFormSaveAndRevokeApproval') {ldelim}
						$('#citationApproved').val('');
					{rdelim}
					
					// Submit the form.
					{if $citation->getId()}
						submitJsonForm('#citationEditorDetailCanvas', 'replace', '#component-grid-citation-citationgrid-row-{$citation->getId()}');
					{else}
						submitJsonForm('#citationEditorDetailCanvas', 'append', '#component-grid-citation-citationgrid-table');
					{/if}

					// Trigger the throbber.
					if (pressedButton === 'citationFormSaveAndApprove') {ldelim}
						$('#citationEditorDetailCanvas').triggerHandler('actionStart');
					{rdelim}
				{rdelim});
			{rdelim});
	
			// Handle highlighting of currently edited citation.
			$('#citationEditorNavPane div.grid .current-item').removeClass('current-item');
			$('#component-grid-citation-citationgrid-row-{$citation->getId()}').addClass('current-item');
	
			// Throbber
			actionThrobber('#citationEditorDetailCanvas');
		{rdelim});
	</script>
	<div class="wrapper">
		<form name="editCitationForm" id="editCitationForm" method="post" action="{url op="updateCitation"}" >
			{include file="controllers/grid/citation/form/citationFormErrorsAndComparison.tpl"}
	
			<div id="citationFormTab-{$tabUid}" class="citation-form-block">
				<ul>
					{* Tabs that contain editable fields *}
					{foreach from=$citationFormTabs key=citationFormTabName item=varsArray}
						<li><a href="#{$citationFormTabName|escape|regex_replace:"/\s*/":""}-{$tabUid}">{$citationFormTabName|escape}</a></li>
					{/foreach}
					
					{* Tabs that contain source data *}
					{foreach from=$citationSourceTabs key=citationSourceTabId item=citationSourceTab}
						<li><a href="#{$citationSourceTabId}-{$tabUid}">{$citationSourceTab.displayName|escape}</a></li>
					{/foreach}
				</ul>
				
				{* Tab content for tabs that contain editable fields *}
				{foreach from=$citationFormTabs key=citationFormTabName item=varsArray}
					{assign var=hasRequiredField value=false}
					<div id="{$citationFormTabName|escape|regex_replace:"/\s*/":""}-{$tabUid}">
						<table width="100%">
							<tr valign="top">
								<td width="30%" class="label">{fieldLabel name="fieldNames" key="submission.citations.grid.fields"}</td>
								<td width="70%" class="value">{fieldLabel name="fieldValues" key="submission.citations.grid.values"}</td>
							</tr>
							{foreach from=$varsArray key=fieldName item=field}
								{if $field.required}{assign var=hasRequiredField value=true}{/if}
								<tr valign="top">
									<td width="30%" class="label">{fieldLabel name=$fieldName key=$field.displayName required=$field.required}</td>
									{capture assign=fieldValueVar}{ldelim}${$fieldName}{rdelim}{/capture}
									{eval|assign:"fieldValue" var=$fieldValueVar}
									<td width="70%" class="value">{fbvElement type="text" name=$fieldName id=$fieldName size=$fbvStyles.size.LARGE maxlength="250" value=$fieldValue"}</td>
								</tr>
							{/foreach}
						</table>
						{if $hasRequiredField}<p><span class="formRequired">{translate key="common.requiredField"}</span></p>{/if}
					</div>
				{/foreach}
				
				{* Tab content for tabs that contain source data *}
				{foreach from=$citationSourceTabs key=citationSourceTabId item=citationSourceTab}
					<div id="{$citationSourceTabId}-{$tabUid}">
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
													tabId = '{/literal}{$tabUid}{literal}';
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
	
			<input type="hidden" name="assocId" value="{$citation->getAssocId()|escape}" />
			<input id="citationApproved" type="hidden" name="citationApproved" value="{if $citationApproved}citationApproved{/if}" />
			<input id="remainsCurrentItem" type="hidden" name="remainsCurrentItem" value="yes" />
			{if $citation->getId()}
				<input type="hidden" name="citationId" value="{$citation->getId()|escape}" />
				<input type="hidden" name="citationState" value="{$citation->getCitationState()|escape}" />
			{/if}
	
			<div class="citation-form-block">
				{include file="linkAction/linkAction.tpl" action=$checkAction id=$containerId}
				<a href="http://scholar.google.com/scholar?ie=UTF-8&oe=UTF-8&hl=en&q={if $citationFormTabs.Filled.nlm30PersonGroupPersonGroupTypeAuthor}author:%22{$nlm30PersonGroupPersonGroupTypeAuthor|escape:'url'}%22+{/if}%22{if $nlm30ConfName}{$nlm30ConfName|escape:'url'}{else}{$nlm30Source|escape:'url'}{/if}%22+{$nlm30ArticleTitle|escape:'url'}{if $nlm30PubIdPubIdTypeDoi}+{$nlm30PubIdPubIdTypeDoi|escape:'url'}{/if}" target="_blank">{translate key="submission.citations.grid.checkGoogleScholar"}</a>
			</div>
	
			<div class="pane_actions citation-form-block">
				<button id="citationFormSaveAndRevokeApproval" type="button" class="citation-save-button secondary-button">{translate key="submission.citations.saveAndRevokeApproval"}</button>
				<button id="citationFormSave" type="button" class="citation-save-button">{if $citation->getId()}{translate key="common.save"}{else}{translate key="common.add"}{/if}</button>
				<button id="citationFormSaveAndApprove" type="button" class="citation-save-button">{if $citation->getId()}{translate key="submission.citations.saveAndApprove"}{else}{translate key="submission.citations.addAndApprove"}{/if}</button>
				<button id="citationFormCancel" type="button">{translate key="common.cancel"}</button>
			</div>
		</form>
	</div>
</div>
