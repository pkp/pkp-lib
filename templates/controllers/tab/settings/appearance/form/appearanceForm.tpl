{**
 * controllers/tab/settings/appearance/form/appearanceForm.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
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
				fetchFileUrl: '{url|escape:javascript op='fetchFile' tab='appearance' escape=false}',
				publishChangeEvents: ['updateHeader', 'updateSidebar']
			{rdelim}
		);
	{rdelim});
</script>

<form id="appearanceForm" class="pkp_form" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="tab.settings.WebsiteSettingsTabHandler" op="saveFormData" tab="appearance"}">
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="appearanceFormNotification"}
	{include file="controllers/tab/settings/wizardMode.tpl" wizardMode=$wizardMode}

	<p>{translate key="manager.setup.appearanceDescription"}</p>

	{* Homepage Content *}
	<div {if $wizardMode}class="pkp_form_hidden"{/if}>
		{fbvFormArea id="homePageContent" title="manager.setup.homepageContent" class="border"}
			{fbvFormSection description="manager.setup.homepageContentDescription"}
			{/fbvFormSection}
			{fbvFormSection list="true" label="manager.setup.newReleases"}
				{fbvElement type="checkbox" label="manager.setup.displayNewReleases" id="displayNewReleases" checked=$displayNewReleases}
			{/fbvFormSection}
			{fbvFormSection label="manager.setup.homepageImage" description="manager.setup.homepageImageDescription"}
				<div id="{$uploadImageLinkActions.homepageImage->getId()}" class="pkp_linkActions">
					{include file="linkAction/linkAction.tpl" action=$uploadImageLinkActions.homepageImage contextId="appearanceForm"}
				</div>
				<div id="homepageImage">
					{$imagesViews.homepageImage}
				</div>
			{/fbvFormSection}
			{fbvFormSection label="manager.setup.additionalContent" description="manager.setup.additionalContentDescription"}
				{fbvElement type="textarea" multilingual=true name="additionalHomeContent" id="additionalHomeContent" value=$additionalHomeContent rich=true}
			{/fbvFormSection}
			{fbvFormSection list="true" label="manager.setup.featuredBooks"}
				{fbvElement type="checkbox" label="manager.setup.displayFeaturedBooks" id="displayFeaturedBooks" checked=$displayFeaturedBooks}
			{/fbvFormSection}
			{fbvFormSection list="true" label="manager.setup.inSpotlight"}
				{fbvElement type="checkbox" label="manager.setup.displayInSpotlight" id="displayInSpotlight" checked=$displayInSpotlight}
			{/fbvFormSection}
		{/fbvFormArea}
	</div>
	{* end Homepage Content *}

	{$additionalHomepageContent}

	{* Page Header *}
	{fbvFormArea id="pageHeader" title="manager.setup.pageHeader" class="border"}
		{fbvFormSection list=true description="manager.setup.pageHeaderDescription" title="manager.setup.contextName"}
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
		<div {if $wizardMode}class="pkp_form_hidden"{/if}>
			{fbvFormSection label="manager.setup.logo" description="manager.setup.useImageLogoDescription"}
			<div id="{$uploadImageLinkActions.pageHeaderLogoImage->getId()}" class="pkp_linkActions">
				{include file="linkAction/linkAction.tpl" action=$uploadImageLinkActions.pageHeaderLogoImage contextId="appearanceForm"}
			</div>
			<div id="pageHeaderLogoImage">
				{$imagesViews.pageHeaderLogoImage}
			</div>
			{/fbvFormSection}
			{fbvFormSection label="manager.setup.alternateHeader" description="manager.setup.alternateHeaderDescription"}
				{fbvElement type="textarea" multilingual=true name="pageHeader" id="pageHeader" value=$pageHeader rich=true}
			{/fbvFormSection}
		</div>
	{/fbvFormArea}
	{* end Page Header *}

	{* Page Footer *}
	{fbvFormArea id="pageFooterContainer" title="manager.setup.pageFooter" class="border"}
		{fbvFormSection description="manager.setup.pageFooterDescription"}
			{fbvElement type="textarea" multilingual=true name="pageFooter" id="pageFooter" value=$pageFooter rich=true}
		{/fbvFormSection}
	{/fbvFormArea}
	{* end Page Footer *}

	{* Layout *}
	{fbvFormArea id="layout" title="manager.setup.layout" class="border"}
		{fbvFormSection title="manager.setup.useStyleSheet" description="manager.setup.styleSheetDescription" size=$fbvStyles.size.MEDIUM inline=true}
			<div id="{$uploadCssLinkAction->getId()}" class="pkp_linkActions">
				{include file="linkAction/linkAction.tpl" action=$uploadCssLinkAction contextId="appearanceForm"}
			</div>
			<div id="styleSheet">
				{$styleSheetView}
			</div>
		{/fbvFormSection}
		{fbvFormSection title="manager.setup.layout.theme" description="manager.setup.layout.themeDescription" size=$fbvStyles.size.MEDIUM inline=true}
			{fbvElement type="select" id="themePluginPath" from=$themePluginOptions selected=$themePluginPath translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection}{/fbvFormSection}{* FIXME: Clear inline *}

		{url|assign:blockPluginsUrl router=$smarty.const.ROUTE_COMPONENT component="listbuilder.settings.BlockPluginsListbuilderHandler" op="fetch" escape=false}
		{load_url_in_div id="blockPluginsContainer" url=$blockPluginsUrl}
	{/fbvFormArea}
	{* end Layout *}

	{* Lists *}
	{fbvFormArea id="advancedAppearanceSettings" title="manager.setup.lists" class="border"}
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
