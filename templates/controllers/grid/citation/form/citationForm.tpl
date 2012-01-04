{**
 * citationForm.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Citation grid form
 *
 * $Id$
 *}


<div id="editCitationFormContainer-{$citation->getId()}">
	<form name="editCitationForm" id="editCitationForm" method="post" action="{url op="updateCitation"}" >
		<h3>{translate key="submission.citations.form.title"}</h3>

		<p>{translate key="submission.citations.form.description"}</p>
		{if $unsavedChanges}
			<p><span class="formError">{translate key="submission.citations.form.unsavedChanges"}</span></p>
		{/if}

		{include file="common/formErrors.tpl"}

		<span class="options">
			{include file="controllers/grid/gridAction.tpl" action=$checkAction id="editCitationFormContainer-"|concat:$citation->getId()}
		</span>

		<table width="100%" class="data">
			<tr valign="top">
				<td width="30%" class="label">{fieldLabel name="editedCitation" key="submission.citations.grid.editedCitation"}</td>
				<td width="70%" class="value"><textarea name="editedCitation" id="editedCitation" cols="40" rows="3" class="textField">{$editedCitation}</textarea></td>
			</tr>
		</table>

		<script type='text/javascript'>
			$(function() {ldelim}
				$.ajaxSetup({ldelim}cache:false{rdelim});
				$("#citationFormTabs-{$ts}").tabs({ldelim} cache: false {rdelim});
			{rdelim});
		</script>

		<div id="citationFormTabs-{$ts}">
			<ul>
				{foreach from=$citationVarArrays key=arrayName item=varsArray}
					<li><a href="#{$arrayName|regex_replace:"/\s*/":""}">{$arrayName|escape}</a></li>
				{/foreach}
			</ul>
			{foreach from=$citationVarArrays key=arrayName item=varsArray}
				<div id="{$arrayName|regex_replace:"/\s*/":""}">
					<table>
						<tr valign="top">
							<td width="30%" class="label">{fieldLabel name="fieldNames" key="submission.citations.grid.fields"}</td>
							<td width="70%" class="value">{fieldLabel name="fieldValues" key="submission.citations.grid.values"}</td>
						</tr>
						{foreach from=$varsArray key=fieldName item=field}
							<tr valign="top">
								<td width="30%" class="label">{fieldLabel name=$fieldName key=$field.translationKey}</td>
								<td width="70%" class="value"><input type="text" name="{$fieldName}" id="{$fieldName}" size="40" maxlength="250" class="textField" value="{$field.value|escape}" /></td>
							</tr>
						{/foreach}
					</table>
				</div>
			{/foreach}
		</div>

		<input type="hidden" name="articleId" value="{$citation->getAssocId()|escape}" />
		{if $citation->getId()}
			<input type="hidden" name="citationId" value="{$citation->getId()|escape}" />
			<input type="hidden" name="citationState" value="{$citation->getCitationState()|escape}" />
		{/if}

		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	</form>
</div>
