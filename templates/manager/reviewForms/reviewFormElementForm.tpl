{**
 * templates/manager/reviewForms/reviewFormElementForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form to create/modify a review form element.
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#reviewFormElementForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<script type="text/javascript">
{literal}
<!--
function togglePossibleResponses(newValue, multipleResponsesElementTypesString) {
	if (multipleResponsesElementTypesString.indexOf(';'+newValue+';') != -1) {
		document.getElementById('reviewFormElementForm').addResponse.disabled=false;
	} else {
		if (document.getElementById('reviewFormElementForm').addResponse.disabled == false) {
			alert({/literal}'{translate|escape:"jsparam" key="manager.reviewFormElement.changeType"}'{literal});
		}
		document.getElementById('reviewFormElementForm').addResponse.disabled=true;
	}
}
// -->
{/literal}
</script>

<form class="pkp_form" id="reviewFormElementForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.reviewForms.ReviewFormElementGridHandler" op="updateReviewFormElement" anchor="possibleResponses"}">
	{fbvElement id="reviewFormId" type="hidden" name="reviewFormId" value=$reviewFormId}
	{fbvElement id="reviewFormElementId" type="hidden" name="reviewFormElementId" value=$reviewFormElementId}

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="reviewFormsNotification"}

	{fbvFormArea id="reviewFormElementForm"}

		<!-- question -->
		{fbvFormSection title="manager.reviewFormElements.question" required=true for="question"}
			{fbvElement type="textarea" id="question" value=$question multilingual=true rich=true}
		{/fbvFormSection}

		<!-- required checkbox -->
		{fbvFormSection for="required" list=true}
			{if $required}
				{assign var="checked" value=true}
			{else}
				{assign var="checked" value=false}
			{/if}
			{fbvElement type="checkbox" id="required" label="manager.reviewFormElements.required" checked=$checked inline="true"}
		{/fbvFormSection}

		<!-- included in message to author checkbox -->
		{fbvFormSection for="included" list=true}
			{if $included}
				{assign var="checked" value=true}
			{else}
				{assign var="checked" value=false}
			{/if}
			{fbvElement type="checkbox" id="included" label="manager.reviewFormElements.included" checked=$checked inline="true"}
		{/fbvFormSection}

		<!-- element type drop-down -->
		{fbvFormSection for="elementType" list=true}
			<!-- when user makes a selection (onchange), warn them if necessary. -->
			<!-- also display/hide options list builder if appropriate. -->
			<!-- look to see how this is done elsewhere under the new JS framework -->
			{fbvElement type="select" label="manager.reviewFormElements.elementType" id="elementType" defaultLabel="" from=$reviewFormElementTypeOptions selected=$elementType size=$fbvStyles.size.MEDIUM required=true}
		{/fbvFormSection}

		<!-- Options listbuilder. Activated for some element types. -->
		<!-- action: delete. field: option text. ordering?? -->
		<!-- when editing a user, 'add role' uses a listbuilder -->
		<!-- see templates/controllers/grid/settings/user/form/userForm.tpl -->
		<div id="elementOptions" class="full left">
			<div>
				<p>reviewFormId: {$reviewFormId}</p>
				<p>reviewFormElementId: {$reviewFormElementId}</p>
			</div>
			<div id="elementOptionsContainer" class="full left">
				{url|assign:elementOptionsUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.settings.reviewForms.ReviewFormElementResponseItemListbuilderHandler" op="fetch" reviewFormId=$reviewFormId reviewFormElementId=$reviewFormElementId title="grid.reviewFormElement.responseItems" escape=false}
				{load_url_in_div id="elementOptionsContainer" url=$elementOptionsUrl}
			</div>
		</div>
		<!-- required field text -->
		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

		<!-- submit button -->
		{fbvFormButtons id="reviewFormElementFormSubmit" submitText="common.save"}
	{/fbvFormArea}
</form>
