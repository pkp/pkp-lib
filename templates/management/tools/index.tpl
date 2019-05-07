{**
 * templates/management/tools/index.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Management index.
 *}
{include file="common/header.tpl" pageTitle="navigation.tools"}

<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#managementTabs').pkpHandler('$.pkp.controllers.TabHandler');
	{rdelim});
</script>
<div id="managementTabs" class="pkp_controllers_tab">
	<ul>
		<li><a name="importexport" href="{url op="importexport"}">{translate key="navigation.tools.importExport"}</a></li>
		<li><a name="statistics" href="{url op="statistics"}">{translate key="manager.statistics.reports"}</a></li>
		<li><a name="permissions" href="{url op="permissions"}">{translate key="settings.libraryFiles.category.permissions"}</a></li>
		{call_hook name="Templates::Management::Settings::tools"}
	</ul>
</div>

{include file="common/footer.tpl"}
