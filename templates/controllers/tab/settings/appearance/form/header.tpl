{**
 * controllers/tab/settings/appearance/form/header.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Form fields for configuring the frontend header
 *
 *}
{fbvFormSection list=true description="manager.setup.pageHeaderDescription" for="pageHeaderTitle" label="manager.setup.contextName"}
	{fbvElement type="text" name="pageHeaderTitle" id="pageHeaderTitle" value=$pageHeaderTitle multilingual=true}
{/fbvFormSection}
{fbvFormSection label="manager.setup.logo" description="manager.setup.useImageLogoDescription" class=$wizardClass}
	<div id="pageHeaderLogoImage">
		{$imagesViews.pageHeaderLogoImage}
	</div>
	<div id="{$uploadImageLinkActions.pageHeaderLogoImage->getId()}" class="pkp_linkActions">
		{include file="linkAction/linkAction.tpl" action=$uploadImageLinkActions.pageHeaderLogoImage contextId="appearanceForm"}
	</div>
{/fbvFormSection}
