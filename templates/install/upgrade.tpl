{**
 * templates/install/upgrade.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Upgrade form.
 *}
{strip}
{assign var="pageTitle" value="installer.upgradeApplication"}
{include file="common/header.tpl"}
{/strip}

{translate key="installer.upgradeInstructions" version=$version->getVersionString(false) baseUrl=$baseUrl}


<div class="separator"></div>


<form class="pkp_form" method="post" action="{url op="installUpgrade"}">
{include file="common/formErrors.tpl"}

{if $isInstallError}
<p>
	<span class="pkp_form_error">{translate key="installer.installErrorsOccurred"}:</span>
	<ul class="pkp_form_error_list">
		<li>{if $dbErrorMsg}{translate key="common.error.databaseError" error=$dbErrorMsg}{else}{translate key=$errorMsg}{/if}</li>
	</ul>
</p>
{/if}

<div class="formButtons">
	{fbvElement class="inline" type="submit" id="installButton" label="installer.upgradeApplication"}
	<div class="clear"></div>
</div>

</form>

{include file="common/footer.tpl"}
