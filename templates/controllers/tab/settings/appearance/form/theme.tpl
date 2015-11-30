{**
 * controllers/tab/settings/appearance/form/theme.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Form fields for selecting the frontend theme
 *
 *}
{fbvFormSection label="manager.setup.layout.theme" for="themePluginPath" description="manager.setup.layout.themeDescription"}
	{fbvElement type="select" id="themePluginPath" from=$themePluginOptions selected=$themePluginPath translate=false}
{/fbvFormSection}
