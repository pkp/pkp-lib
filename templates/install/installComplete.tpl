{**
 * installComplete.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display confirmation of successful installation.
 * If necessary, will also display new config file contents if config file could not be written.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="installer.installApplication"}
	</h1>

	<div class="app__contentPanel">

		{capture assign="loginUrl"}{url page="login"}{/capture}
		{translate key="installer.installationComplete" loginUrl=$loginUrl}

		{if $writeConfigFailed}
			{translate key="installer.overwriteConfigFileInstructions"}

			<form class="pkp_form" action="#">
				<p>
				{translate key="installer.contentsOfConfigFile"}:<br />
				<textarea name="config" cols="80" rows="20" class="textArea" style="font-family: Courier,'Courier New',fixed-width">{$configFileContents|escape}</textarea>
				</p>
			</form>
		{/if}
	</div>
{/block}
