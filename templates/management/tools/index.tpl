{**
 * templates/management/tools/index.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Management index.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="navigation.tools"}
	</h1>

	<script type="text/javascript">
		// Attach the JS file tab handler.
		$(function() {ldelim}
			$('#managementTabs').pkpHandler('$.pkp.controllers.TabHandler');
		{rdelim});
	</script>
	<div id="managementTabs" class="pkp_controllers_tab">
		<ul>
			<li><a name="importexport" href="{url op="importexport"}">{translate key="navigation.tools.importExport"}</a></li>
			<li><a name="permissions" href="{url op="permissions"}">{translate key="settings.libraryFiles.category.permissions"}</a></li>
			{call_hook name="Templates::Management::Settings::tools"}
		</ul>
	</div>
{/block}
