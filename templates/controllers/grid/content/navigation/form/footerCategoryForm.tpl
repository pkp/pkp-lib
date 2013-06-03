{**
 * templates/controllers/grid/content/navigation/form/footerCategoryForm.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form to read/create/edit footer categories.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#footerCategoryForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="footerCategoryForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.content.navigation.ManageFooterGridHandler" op="updateFooterCategory"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="footerFormNotification"}
	{fbvFormArea id="categoryInfo"}
		{if $footerCategory}
			<input type="hidden" name="footerCategoryId" value="{$footerCategory->getId()|escape}" />
		{/if}

		{fbvFormSection for="title" title="common.title" required="true"}
			{fbvElement type="text" multilingual="true" id="title" value=$title maxlength="255" size=$fbvStyles.size.MEDIUM inline="true"}
		{/fbvFormSection}

		{fbvFormSection for="path" title="grid.category.path" required="true"}
			{fbvElement type="text" id="path" value=$path|escape maxlength="255" size=$fbvStyles.size.MEDIUM inline="true"}
		{/fbvFormSection}

		{fbvFormSection title="common.description" required="true"}
			{fbvElement type="textarea" multilingual=true name="description" id="description" value=$description rich=true height=$fbvStyles.height.SHORT}
		{/fbvFormSection}

		{if $footerCategory}
			{url|assign:footerLinkListbuilderUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.content.navigation.FooterLinkListbuilderHandler" op="fetch" footerCategoryId=$footerCategory->getId() escape=false}
		{else}
			{url|assign:footerLinkListbuilderUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.content.navigation.FooterLinkListbuilderHandler" op="fetch" escape=false}
		{/if}

		{load_url_in_div id="footerLinkListbuilderContainer" url=$footerLinkListbuilderUrl}

	{/fbvFormArea}
	{fbvFormButtons submitText="common.save"}
</form>
