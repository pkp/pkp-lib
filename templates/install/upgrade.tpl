{**
 * templates/install/upgrade.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Upgrade form.
 *
 *}
{strip}
{include file="common/header.tpl"}
{/strip}

{translate key="installer.upgradeInstructions" version=$version->getVersionString() baseUrl=$baseUrl}


<div class="separator"></div>


<form method="post" action="{url op="installUpgrade"}">
{include file="common/formErrors.tpl"}

{if $isInstallError}
	<div id="installError">
		<p>
			<span class="pkp_form_error">{translate key="installer.installErrorsOccurred"}:</span>
			<ul class="pkp_form_error_list">
				<li>{if $dbErrorMsg}{translate key="common.error.databaseError" error=$dbErrorMsg}{else}{translate key=$errorMsg}{/if}</li>
			</ul>
		</p>
	</div>{* installError *}
{/if}

<p><input type="submit" value="{translate key="installer.upgradeApplication"}" class="button defaultButton" /></p>

</form>

{include file="common/footer.tpl"}
