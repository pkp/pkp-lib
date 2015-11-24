{**
 * controllers/tab/settings/appearance/form/homepageImage.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Form fields for uploading a frontend homepage image
 *
 *}
{assign var="upload_image_field_id" value=$uploadImageLinkActions.homepageImage->getId()}
{fbvFormSection for="$upload_image_field_id" label="manager.setup.homepageImage" description="manager.setup.homepageImageDescription"}
	<div id="homepageImage">
		{$imagesViews.homepageImage}
	</div>
	<div id="$upload_image_field_id" class="pkp_linkActions">
		{include file="linkAction/linkAction.tpl" action=$uploadImageLinkActions.homepageImage contextId="appearanceForm"}
	</div>
{/fbvFormSection}
