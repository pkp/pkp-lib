{**
 * controllers/tab/settings/appearance/form/stylesheet.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Form fields for uploading a custom frontend stylesheet
 *
 *}
{assign var="stylesheet_field_id" value=$uploadCssLinkAction->getId()}
{fbvFormSection label="manager.setup.useStyleSheet" for=$stylesheet_field_id description="manager.setup.styleSheetDescription"}
	<div id="styleSheet">
		{$styleSheetView}
	</div>
	<div id={$stylesheet_field_id} class="pkp_linkActions">
		{include file="linkAction/linkAction.tpl" action=$uploadCssLinkAction contextId="appearanceForm"}
	</div>
{/fbvFormSection}
