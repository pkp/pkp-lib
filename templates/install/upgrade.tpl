{**
 * templates/install/upgrade.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Upgrade form.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="installer.upgradeApplication"}
	</h1>

	<div class="app__contentPanel">
		{translate key="installer.upgradeInstructions" version=$version->getVersionString(false) baseUrl=$baseUrl}

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
				{fbvElement type="submit" id="installButton" label="installer.upgradeApplication"}
			</div>

		</form>
	</div>
{/block}
