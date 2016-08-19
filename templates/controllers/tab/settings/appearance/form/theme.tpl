{**
 * controllers/tab/settings/appearance/form/theme.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Form fields for selecting the frontend theme
 *
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#selectTheme').pkpHandler('$.pkp.controllers.form.ThemeOptionsHandler');
	{rdelim});
</script>

{fbvFormArea id="selectTheme"}
	{fbvFormSection label="manager.setup.layout.theme" for="themePluginPath" description="manager.setup.layout.themeDescription"}
		{fbvElement type="select" id="themePluginPath" from=$enabledThemes selected=$themePluginPath translate=false}
	{/fbvFormSection}

	{if count($activeThemeOptions)}
		{fbvFormArea id="activeThemeOptions"}
			{foreach from=$activeThemeOptions key=themeOptionName item=themeOption}

				{if $themeOption.type == 'text'}
					{fbvFormSection label=$themeOption.label}
						{fbvElement type="text" id="themeOption_"|concat:$themeOptionName value=$themeOption.value|escape label=$themeOption.description}
					{/fbvFormSection}

				{elseif $themeOption.type == 'radio'}
					{fbvFormSection label=$themeOption.label list=true}
						{foreach from=$themeOption.options key=themeOptionItemName item=themeOptionItem}
							{fbvElement type="radio" id="themeOption_"|concat:$themeOptionName|concat:$themeOptionItemName name="themeOption_"|concat:$themeOptionName value=$themeOptionItemName checked=$themeOption.value|compare:$themeOptionItemName label=$themeOptionItem}
						{/foreach}
					{/fbvFormSection}

				{elseif $themeOption.type == 'color'}
					{fbvFormSection label=$themeOption.label}
						{fbvElement type="color" id="themeOption_"|concat:$themeOptionName value=$themeOption.value|escape default=$themeOption.default label=$themeOption.description}
					{/fbvFormSection}
				{/if}
			{/foreach}
		{/fbvFormArea}
	{/if}
{/fbvFormArea}
