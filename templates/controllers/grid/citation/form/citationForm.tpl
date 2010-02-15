
{**
 * citationForm.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Citation grid form
 *
 * $Id$
 *}
<div id="editCitationFormContainer-{$citationId}">
	<form name="editCitationForm" id="editCitationForm" method="post" action="{url op="updateCitation"}" >
		<h3>{translate key="submission.citations.form.title"}</h3>

		<p>{translate key="submission.citations.form.description"}</p>

		<span class="options">
			{include file="controllers/grid/gridAction.tpl" action=$parseAction id="editCitationFormContainer-`$citationId`"}
			{include file="controllers/grid/gridAction.tpl" action=$lookupAction id="editCitationFormContainer-`$citationId`"}
		</span>

		{include file="common/formErrors.tpl"}

		<table width="100%" class="data">
			<tr valign="top">
				<td width="30%" class="label">{fieldLabel name="editedCitation" key="submission.citations.grid.editedCitation"}</td>
				<td width="70%" class="value"><textarea name="editedCitation" id="editedCitation" cols="40" rows="3" class="textField">{$editedCitation}</textarea></td>
			</tr>
			{foreach from=$citationVars key=fieldName item=field}
				<tr valign="top">
					<td width="30%" class="label">{fieldLabel name=$fieldName key=$field.translationKey}</td>
					<td width="70%" class="value"><input type="text" name="{$fieldName}" id="{$fieldName}" size="40" maxlength="90" class="textField" value="{$field.value|escape}" /></td>
				</tr>
			{/foreach}
		</table>

		<input type="hidden" name="articleId" value="{$citation->getAssocId()|escape}" />
		{if $citation->getId()}
			<input type="hidden" name="citationId" value="{$citation->getId()|escape}" />
		{/if}

		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	</form>
</div>