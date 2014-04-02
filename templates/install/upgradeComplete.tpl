{**
 * templates/install/upgradeComplete.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display confirmation of successful upgrade.
 * If necessary, will also display new config file contents if config file could not be written.
 *
 *}
{strip}
{include file="common/header.tpl"}
{/strip}

{translate key="installer.upgradeComplete" version=$newVersion->getVersionString()}

{if !empty($notes)}
<div id="releaseNotes">
<h4>{translate key="installer.releaseNotes"}</h4>
{foreach from=$notes item=note}
<p><pre style="font-size: 125%">{$note|escape}</pre></p>
{/foreach}
</div>
{/if}

{if $writeConfigFailed}
	<div id="writeConfigFailed">
		{translate key="installer.overwriteConfigFileInstructions"}

		<form action="#">
			<p>
				{translate key="installer.contentsOfConfigFile"}:<br />
				<textarea name="config" cols="80" rows="20" class="textArea" style="font-family: Courier,'Courier New',fixed-width">{$configFileContents|escape}</textarea>
			</p>
		</form>
	</div>{* writeConfigFailed *}
{/if}

{include file="common/footer.tpl"}
