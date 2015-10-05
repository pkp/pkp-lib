{**
 * controllers/tab/settings/appearance/form/appearanceForm.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Website appearance management form.
 *
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#appearanceForm').pkpHandler('$.pkp.controllers.tab.settings.form.FileViewFormHandler',
			{ldelim}
				fetchFileUrl: {url|json_encode op='fetchFile' tab='appearance' escape=false}
			{rdelim}
		);
	{rdelim});
</script>

{* In wizard mode, these fields should be hidden *}
{if $wizardMode}
	{assign var="wizard_class" value="is_wizard_mode"}
{else}
	{assign var="wizard_class" value=""}
{/if}

<form id="appearanceForm" class="pkp_form" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.WebsiteSettingsTabHandler" op="saveFormData" tab="appearance"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="appearanceFormNotification"}
	{include file="controllers/tab/settings/wizardMode.tpl" wizardMode=$wizardMode}

	{* Homepage Content *}
	{fbvFormArea id="homePageContent" title="manager.setup.homepageContent" class=$wizard_class}
		<div class="description">
			{translate key="manager.setup.homepageContentDescription"}
		</div>

		{$newContentFormContent}

		{assign var="upload_image_field_id" value=$uploadImageLinkActions.homepageImage->getId()}
		{fbvFormSection for="$upload_image_field_id" label="manager.setup.homepageImage" description="manager.setup.homepageImageDescription"}
			<div id="$upload_image_field_id" class="pkp_linkActions">
				{include file="linkAction/linkAction.tpl" action=$uploadImageLinkActions.homepageImage contextId="appearanceForm"}
			</div>
			<div id="homepageImage">
				{$imagesViews.homepageImage}
			</div>
		{/fbvFormSection}
		{fbvFormSection for="additionalHomeContent" label="manager.setup.additionalContent" description="manager.setup.additionalContentDescription"}
			{fbvElement type="textarea" multilingual=true name="additionalHomeContent" id="additionalHomeContent" value=$additionalHomeContent rich=true}
		{/fbvFormSection}

		{$featuredContentFormContent}
	{/fbvFormArea}

	{$additionalHomepageContent}

	{* end Homepage Content *}

	{* Page Header *}
	{fbvFormArea id="pageHeader" title="manager.setup.pageHeader"}
		{fbvFormSection list=true description="manager.setup.pageHeaderDescription" label="manager.setup.contextName"}
			{fbvElement type="radio" name="pageHeaderTitleType[$locale]" id="pageHeaderTitleType-0" value=0 checked=!$pageHeaderTitleType[$locale] label="manager.setup.useTextTitle"}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="text" name="pageHeaderTitle" id="pageHeaderTitle" value=$pageHeaderTitle multilingual=true}
		{/fbvFormSection}
		{fbvFormSection list=true}
			{fbvElement type="radio" name="pageHeaderTitleType[$locale]" id="pageHeaderTitleType-1" value=1 checked=$pageHeaderTitleType[$locale] label="manager.setup.useImageTitle" inline=true}
			<div id="{$uploadImageLinkActions.pageHeaderTitleImage->getId()}" class="pkp_linkActions inline">
				{include file="linkAction/linkAction.tpl" action=$uploadImageLinkActions.pageHeaderTitleImage contextId="appearanceForm"}
			</div>
			<div id="pageHeaderTitleImage">
				{$imagesViews.pageHeaderTitleImage}
			</div>
		{/fbvFormSection}
		{fbvFormSection label="manager.setup.logo" description="manager.setup.useImageLogoDescription" class=$wizard_class}
		<div id="{$uploadImageLinkActions.pageHeaderLogoImage->getId()}" class="pkp_linkActions">
			{include file="linkAction/linkAction.tpl" action=$uploadImageLinkActions.pageHeaderLogoImage contextId="appearanceForm"}
		</div>
		<div id="pageHeaderLogoImage">
			{$imagesViews.pageHeaderLogoImage}
		</div>
		{/fbvFormSection}
		{fbvFormSection label="manager.setup.alternateHeader" description="manager.setup.alternateHeaderDescription" class=$wizard_class}
			{fbvElement type="textarea" multilingual=true name="pageHeader" id="pageHeader" value=$pageHeader rich=true}
		{/fbvFormSection}
	{/fbvFormArea}
	{* end Page Header *}

	{* Page Footer *}
	{fbvFormArea id="pageFooterContainer" title="manager.setup.pageFooter"}
		{fbvFormSection description="manager.setup.pageFooterDescription"}
			{fbvElement type="textarea" multilingual=true name="pageFooter" id="pageFooter" value=$pageFooter rich=true}
		{/fbvFormSection}
	{/fbvFormArea}
	{* end Page Footer *}

	{* Layout *}
	{fbvFormArea id="layout"}
		{assign var="stylesheet_field_id" value=$uploadCssLinkAction->getId()}
		{fbvFormSection label="manager.setup.useStyleSheet" for=$stylesheet_field_id description="manager.setup.styleSheetDescription"}
			<div id={$stylesheet_field_id} class="pkp_linkActions">
				{include file="linkAction/linkAction.tpl" action=$uploadCssLinkAction contextId="appearanceForm"}
			</div>
			<div id="styleSheet">
				{$styleSheetView}
			</div>
		{/fbvFormSection}
		{fbvFormSection label="manager.setup.layout.theme" for="themePluginPath" description="manager.setup.layout.themeDescription"}
			{fbvElement type="select" id="themePluginPath" from=$themePluginOptions selected=$themePluginPath translate=false}
		{/fbvFormSection}

		{url|assign:blockPluginsUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.settings.BlockPluginsListbuilderHandler" op="fetch" escape=false}
		{load_url_in_div id="blockPluginsContainer" url=$blockPluginsUrl}
	{/fbvFormArea}
	{* end Layout *}

	{$additionalAppearanceSettings}

	{* Lists *}
	{fbvFormArea id="advancedAppearanceSettings" title="manager.setup.lists"}
		{fbvFormSection description="manager.setup.listsDescription"}
			{fbvElement type="text" id="itemsPerPage" value=$itemsPerPage size=$fbvStyles.size.SMALL label="common.itemsPerPage"}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="text" id="numPageLinks" value=$numPageLinks size=$fbvStyles.size.SMALL label="manager.setup.numPageLinks"}
		{/fbvFormSection}
	{/fbvFormArea}
	{* end Lists *}

	{if !$wizardMode}
		{fbvFormButtons id="appearanceFormSubmit" submitText="common.save" hideCancel=true}
	{/if}
</form>
