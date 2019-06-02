{**
 * templates/controllers/grid/navigationMenus/customNMIType.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Custom Custom NMI Type edit form part
 *}
<div id="NMI_TYPE_CUSTOM" class="NMI_TYPE_CUSTOM_EDIT">
	{fbvFormSection}
		{fbvFormSection title="manager.navigationMenus.form.path" for="path" required="true"}
			{fbvElement type="text" id="path" value=$path required="true"}
			<p>
				{capture assign=exampleUrl}{url|replace:"REPLACEME":"%PATH%" router=$smarty.const.ROUTE_PAGE page="REPLACEME"}{/capture}
				{translate key="manager.navigationMenus.form.viewInstructions" pagesPath=$exampleUrl}
			</p>
		{/fbvFormSection}
		{fbvFormSection label="manager.navigationMenus.form.content" for="content"}
			{fbvElement type="textarea" multilingual=true name="content" id="content" value=$content rich=true height=$fbvStyles.height.TALL variables=$allowedVariables}
		{/fbvFormSection}
	{/fbvFormSection}

	{fbvFormSection class="formButtons"}
		{fbvElement type="button" class="pkp_button_link" id="previewButton" label="common.preview"}
	{/fbvFormSection}
</div>

