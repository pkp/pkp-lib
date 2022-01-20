{**
 * plugins/importexport/native/templates/resultsExport.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Result of operations this plugin performed
 *}
{if $errorsFound}
	{translate key="plugins.importexport.native.processFailed"}
{else}
	{translate key="plugins.importexport.native.export.completed"}
	{translate key="plugins.importexport.native.export.completed.downloadFile"}

	<div id="exportIssues-tab">
		<script type="text/javascript">
			$(function() {ldelim}
				// Attach the form handler.
				$('#exportIssuesXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
			{rdelim});
		</script>
		<form id="exportIssuesXmlForm" class="pkp_form" action="{plugin_url path="downloadExportFile"}" method="post">
			{csrf}
			<input type="hidden" name="downloadFilePath" id="downloadFilePath" value="{$exportPath}" />
			{fbvFormArea id="issuesXmlForm"}
				{fbvFormButtons submitText="plugins.importexport.native.export.download.results" hideCancel="true"}
			{/fbvFormArea}
		</form>
	</div>
{/if}

{include file='core:plugins/importexport/innerResults.tpl' key='warnings' errorsAndWarnings=$errorsAndWarnings}
{include file='core:plugins/importexport/innerResults.tpl' key='errors' errorsAndWarnings=$errorsAndWarnings}

{if $validationErrors}
	<h2>{translate key="plugins.importexport.common.validationErrors"}</h2>
	<ul>
		{foreach from=$validationErrors item=validationError}
			<li>{$validationError->message|escape}</li>
		{/foreach}
	</ul>
{/if}
