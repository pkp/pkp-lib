{**
 * controllers/tab/settings/appearance/form/journalFavicon.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Form fields for uploading a journal favicon
 *
 *}
{assign var="uploadImageFieldId" value=$uploadImageLinkActions.journalFavicon->getId()}
{fbvFormSection for="$uploadImageFieldId" label="manager.setup.journalFavicon" description="manager.setup.journalFaviconDescription"}
	<div id="journalFavicon">
		{$imagesViews.journalFavicon}
	</div>
	<div id="$uploadImageFieldId" class="pkp_linkActions">
		{include file="linkAction/linkAction.tpl" action=$uploadImageLinkActions.journalFavicon contextId="appearanceForm"}
	</div>
{/fbvFormSection}
