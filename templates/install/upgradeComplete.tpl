{**
 * upgradeComplete.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display confirmation of successful upgrade.
 * If necessary, will also display new config file contents if config file could not be written.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="installer.upgradeApplication"}
	</h1>

	<div class="app__contentPanel">

		{translate key="installer.upgradeComplete" version=$newVersion->getVersionString(false)}

		{if !empty($notes)}
			<h4>{translate key="installer.releaseNotes"}</h4>
			{foreach from=$notes item=note}
				<p><pre style="font-size: 125%">{$note|escape}</pre></p>
			{/foreach}
		{/if}

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
