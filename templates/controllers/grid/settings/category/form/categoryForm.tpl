{**
 * lib/pkp/templates/controllers/grid/settings/category/form/categoryForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form to edit or create a category
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#categoryForm').pkpHandler(
			'$.pkp.controllers.form.FileUploadFormHandler',
			{ldelim}
				publishChangeEvents: ['updateSidebar'],
				$uploader: $('#plupload'),
				uploaderOptions: {ldelim}
					uploadUrl: {url|json_encode op="uploadImage" escape=false},
					baseUrl: {$baseUrl|json_encode},
					filters: {ldelim}
						mime_types : [
							{ldelim} title : "Image files", extensions : "jpg,jpeg,png" {rdelim}
						]
					{rdelim}
				{rdelim}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="categoryForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.settings.category.CategoryCategoryGridHandler" op="updateCategory" categoryId=$categoryId}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="categoryFormNotification"}

	{fbvFormArea id="categoryDetails"}

		<h3>{translate key="grid.category.categoryDetails"}</h3>

		{fbvFormSection title="grid.category.name" for="name" required="true"}
			{fbvElement type="text" multilingual="true" name="name" value=$name id="name" required="true"}
		{/fbvFormSection}

		{fbvFormSection title="grid.category.parentCategory" for="context"}
			{fbvElement type="select" id="parentId" from=$rootCategories selected=$parentId translate=false disabled=$cannotSelectChild}
		{/fbvFormSection}

		{fbvFormSection title="grid.category.path" required=true for="path"}
			{capture assign="sampleUrl"}
				{url router=PKP\core\PKPApplication::ROUTE_PAGE page="catalog" op="category" path="path"}
			{/capture}
			{capture assign="instruct"}
				{translate key="grid.category.urlWillBe" sampleUrl=$sampleUrl}
			{/capture}
			{fbvElement type="text" id="path" value=$path maxlength="32" label=$instruct subLabelTranslate=false}
		{/fbvFormSection}

		{fbvFormSection title="grid.category.description" for="context"}
			{fbvElement type="textarea" multilingual="true" id="description" value=$description rich=true}
		{/fbvFormSection}

		{fbvFormSection label="catalog.sortBy" description="catalog.sortBy.categoryDescription" for="sortOption"}
			{fbvElement type="select" id="sortOption" from=$sortOptions selected=$sortOption translate=false}
		{/fbvFormSection}

		{fbvFormSection title="category.coverImage"}
			{include file="controllers/fileUploadContainer.tpl" id="plupload"}
			<input type="hidden" name="temporaryFileId" id="temporaryFileId" value="" />
		{/fbvFormSection}

		{if $image}
			{fbvFormSection}
				{capture assign="altTitle"}{translate key="submission.currentCoverImage"}{/capture}
				<img class="pkp_helpers_container_center" height="{$image.thumbnailHeight}" width="{$image.thumbnailWidth}" src="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="catalog" op="thumbnail" type="category" id=$categoryId}" alt="{$altTitle|escape}" />
			{/fbvFormSection}
		{/if}

		{fbvFormSection list=true title="manager.sections.form.assignEditors"}
		<div>{translate key="manager.categories.form.assignEditors.description"}</div>
		{foreach from=$assignableUserGroups item="assignableUserGroup"}
			{assign var="role" value=$assignableUserGroup.userGroup->getLocalizedName()}
			{assign var="userGroupId" value=$assignableUserGroup.userGroup->getId()}
			{foreach from=$assignableUserGroup.users item=$username key="id"}
				{fbvElement
					type="checkbox"
					id="subEditors[{$userGroupId}][]"
					value=$id
					checked=(isset($subeditorUserGroups[$id]) && in_array($userGroupId, $subeditorUserGroups[$id]))
					label={translate key="manager.sections.form.assignEditorAs" name=$username|escape role=$role|escape}
					translate=false
				}
			{/foreach}
		{/foreach}
		{/fbvFormSection}

		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
		{fbvFormButtons}

	{/fbvFormArea}
</form>
