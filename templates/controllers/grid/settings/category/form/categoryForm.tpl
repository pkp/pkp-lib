{**
 * lib/pkp/templates/controllers/grid/settings/category/form/categoryForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
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
							{ldelim} title : "Image files", extensions : "jpg,jpeg,png,svg" {rdelim}
						]
					{rdelim}
				{rdelim}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="categoryForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.category.CategoryCategoryGridHandler" op="updateCategory" categoryId=$categoryId}">
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
			{capture assign="instruct"}
				{url router=$smarty.const.ROUTE_PAGE page="catalog" op="category" path="path"}
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
				<img class="pkp_helpers_container_center" height="{$image.thumbnailHeight}" width="{$image.thumbnailWidth}" src="{url router=$smarty.const.ROUTE_PAGE page="catalog" op="thumbnail" type="category" id=$categoryId}" alt="{$altTitle|escape}" />
			{/fbvFormSection}
		{/if}

		{if $hasSubEditors}
			{fbvFormSection}
				{assign var="uuid" value=""|uniqid|escape}
				<div id="subeditors-{$uuid}">
					<list-panel
						v-bind="components.subeditors"
						@set="set"
					/>
				</div>
				<script type="text/javascript">
					pkp.registry.init('subeditors-{$uuid}', 'Container', {$subEditorsListData|json_encode});
				</script>
			{/fbvFormSection}
		{/if}

		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
		{fbvFormButtons}

	{/fbvFormArea}
</form>
