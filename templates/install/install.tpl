{**
 * templates/install/install.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Installation form.
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div class="legacyDefaults">
	<h1 class="app__pageHeading">
		{translate key="installer.appInstallation"}
	</h1>

	{capture assign="upgradeUrl"}{url page="install" op="upgrade"}{/capture}
	<notification>
		{translate key="installer.updatingInstructions" upgradeUrl=$upgradeUrl}
	</notification>
	<br />

	<div class="app__contentPanel">
		<form class="pkp_form" method="post" id="installForm" action="{url op="install"}">
			{fbvFormSection label="common.language" for="installLanguage" style="position: absolute;"}
				{fbvElement type="select" name="installLanguage" id="installLanguage" from=$languageOptions selected=$locale translate=false size=$fbvStyles.size.SMALL subLabelTranslate=true}
			{/fbvFormSection}

			{capture assign="writable_config"}{if is_writeable('config.inc.php')}{translate key="installer.checkYes"}{else}{translate key="installer.checkNo"}{/if}{/capture}
			{capture assign="writable_cache"}{if is_writeable('cache')}{translate key="installer.checkYes"}{else}{translate key="installer.checkNo"}{/if}{/capture}
			{capture assign="writable_public"}{if is_writeable('public')}{translate key="installer.checkYes"}{else}{translate key="installer.checkNo"}{/if}{/capture}
			{capture assign="writable_db_cache"}{if is_writeable('cache/_db')}{translate key="installer.checkYes"}{else}{translate key="installer.checkNo"}{/if}{/capture}
			{capture assign="writable_templates_cache"}{if is_writeable('cache/t_cache')}{translate key="installer.checkYes"}{else}{translate key="installer.checkNo"}{/if}{/capture}
			{capture assign="writable_templates_compile"}{if is_writeable('cache/t_compile')}{translate key="installer.checkYes"}{else}{translate key="installer.checkNo"}{/if}{/capture}

			{if !$phpIsSupportedVersion}
				{capture assign="wrongPhpText"}{translate key="installer.installationWrongPhp"}{/capture}
			{/if}

			<script>
				$(function() {ldelim}
					// Attach the form handler.
					$('#installForm').pkpHandler('$.pkp.controllers.form.FormHandler');
					$('#installLanguage').change(function () {
						var params = new URLSearchParams(location.search);
						params.set('setLocale', this.value);
						location = location.href.replace(/(\?.*)?$/, '?' + params);
					});
				{rdelim});
			</script>

			<input type="hidden" name="installing" value="0" />

			{translate key="installer.installationInstructions" version=$version->getVersionString(false) upgradeUrl=$upgradeUrl baseUrl=$baseUrl writable_config=$writable_config writable_db_cache=$writable_db_cache writable_cache=$writable_cache writable_public=$writable_public writable_templates_cache=$writable_templates_cache writable_templates_compile=$writable_templates_compile phpRequiredVersion=$phpRequiredVersion wrongPhpText=$wrongPhpText phpVersion=$phpVersion}

			{if $isInstallError}
				{* The notification framework requires user sessions, which are not available on install. Use the template directly. *}
				<div class="pkp_notification">
					{if $dbErrorMsg}
						{capture assign="errorMsg"}{translate key="common.error.databaseError" error=$dbErrorMsg}{/capture}
					{elseif $translateErrorMsg}
						{capture assign="errorMsg"}{translate key=$errorMsg}{/capture}
					{/if}
					{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=installer notificationStyleClass=notifyError notificationTitle="installer.installErrorsOccurred"|translate notificationContents=$errorMsg}
				</div>
			{/if}

			<!-- XSL check -->
			{if $xslRequired && !$xslEnabled}
				{* The notification framework requires user sessions, which are not available on install. Use the template directly. *}
				<div class="pkp_notification">
					{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=installerXsl notificationStyleClass=notifyWarning notificationTitle="common.warning"|translate notificationContents="installer.configureXSLMessage"|translate}
				</div>
			{/if}

			{fbvFormArea id="preInstallationFormArea" title="installer.preInstallationInstructionsTitle"}
				{translate key="installer.preInstallationInstructions" upgradeUrl=$upgradeUrl baseUrl=$baseUrl writable_config=$writable_config writable_db_cache=$writable_db_cache writable_cache=$writable_cache writable_public=$writable_public writable_templates_cache=$writable_templates_cache writable_templates_compile=$writable_templates_compile phpRequiredVersion=$phpRequiredVersion wrongPhpText=$wrongPhpText phpVersion=$phpVersion}
			{/fbvFormArea}

			<!-- Administrator username, password, and email -->
			{fbvFormArea id="administratorAccountFormArea" title="installer.administratorAccount"}
				<p>{translate key="installer.administratorAccountInstructions"}</p>
				{fbvFormSection label="user.username"}
					{fbvElement type="text" id="adminUsername" value=$adminUsername maxlength="32" size=$fbvStyles.size.MEDIUM}
				{/fbvFormSection}
				{fbvFormSection label="user.password"}
					{fbvElement type="text" password=true id="adminPassword" value=$adminPassword maxlength="32" size=$fbvStyles.size.MEDIUM}
				{/fbvFormSection}
				{fbvFormSection label="user.repeatPassword"}
					{fbvElement type="text" password=true id="adminPassword2" value=$adminPassword2 maxlength="32" size=$fbvStyles.size.MEDIUM}
				{/fbvFormSection}
				{fbvFormSection label="user.email"}
					{fbvElement type="text" id="adminEmail" value=$adminEmail maxlength="90" size=$fbvStyles.size.MEDIUM}
				{/fbvFormSection}
			{/fbvFormArea}

			<!-- Locale configuration -->
			{fbvFormArea id="localeSettingsFormArea" title="installer.localeSettings" title="installer.localeSettings"}
				<p>{translate key="installer.localeSettingsInstructions" supportsMBString=$supportsMBString}</p>
				{fbvFormSection label="locale.primary" description="installer.localeInstructions" for="locale"}
					{fbvElement type="select" name="locale" id="localeOptions" from=$localeOptions selected=$locale translate=false size=$fbvStyles.size.SMALL subLabelTranslate=true}
				{/fbvFormSection}
				{fbvFormSection list="true" label="installer.additionalLocales" description="installer.additionalLocalesInstructions"}
					{foreach from=$localeOptions key=localeKey item=localeName}
						{assign var=localeKeyEscaped value=$localeKey|escape}
						{if !$localesComplete[$localeKey]}
							{assign var=localeName value=$localeName|concat:"*"}
						{/if}
						{if in_array($localeKey,$additionalLocales)}
							{assign var=localeSelected value=true}
						{else}
							{assign var=localeSelected value=false}
						{/if}
						{fbvElement type="checkbox" name="additionalLocales[]" id="additionalLocales-$localeKeyEscaped" value=$localeKeyEscaped translate=false label="manager.people.createUserSendNotify" checked=$localeSelected label=$localeName|escape}
					{/foreach}
				{/fbvFormSection}
				{fbvFormSection label="timeZone" description="installer.timezoneInstructions" for="timeZone"}
					{fbvElement type="select" name="timeZone" id="timeZoneOptions" from=$timeZoneOptions selected=$timeZone translate=false size=$fbvStyles.size.SMALL subLabelTranslate=true}
				{/fbvFormSection}
			{/fbvFormArea}

			<!-- Files directory configuration -->
			{if !$skipFilesDirSection}
				{fbvFormArea id="fileSettingsFormArea" title="installer.fileSettings"}
					{fbvFormSection label="installer.filesDir" description="installer.filesDirInstructions"}
						{fbvElement type="text" id="filesDir" value=$filesDir maxlength="255" size=$fbvStyles.size.LARGE}
					{/fbvFormSection}
					<p>{translate key="installer.allowFileUploads" allowFileUploads=$allowFileUploads}</p>
					<p>{translate key="installer.maxFileUploadSize" maxFileUploadSize=$maxFileUploadSize}</p>
				{/fbvFormArea}
			{/if}{* !$skipFilesDirSection *}

			<!-- Database configuration -->
			{fbvFormArea id="databaseSettingsFormArea" title="installer.databaseSettings"}
				<p>{translate key="installer.databaseSettingsInstructions"}</p>
				{fbvFormSection label="installer.databaseDriver" description="installer.databaseDriverInstructions"}
					{fbvElement type="select" id="databaseDriver" from=$databaseDriverOptions selected=$databaseDriver translate=false size=$fbvStyles.size.SMALL}
				{/fbvFormSection}
				{fbvFormSection label="installer.databaseHost"}
					{fbvElement type="text" id="databaseHost" value=$databaseHost maxlength="60" size=$fbvStyles.size.MEDIUM}
				{/fbvFormSection}
				{fbvFormSection label="installer.databaseUsername"}
					{fbvElement type="text" id="databaseUsername" value=$databaseUsername maxlength="60" size=$fbvStyles.size.MEDIUM}
				{/fbvFormSection}
				{fbvFormSection label="installer.databasePassword"}
					{fbvElement type="text" password=true id="databasePassword" value=$databasePassword maxlength="60" size=$fbvStyles.size.MEDIUM}
				{/fbvFormSection}
				{fbvFormSection label="installer.databaseName"}
					{fbvElement type="text" id="databaseName" value=$databaseName maxlength="60" size=$fbvStyles.size.MEDIUM}
				{/fbvFormSection}
			{/fbvFormArea}

			{fbvFormArea id="oaiSettingsFormArea" title="installer.oaiSettings"}
				{fbvFormSection label="installer.oaiRepositoryId" description="installer.oaiRepositoryIdInstructions"}
					{fbvElement type="text" id="oaiRepositoryId" value=$oaiRepositoryId maxlength="60" size=$fbvStyles.size.LARGE}
				{/fbvFormSection}
			{/fbvFormArea}

			{fbvFormArea id="beaconArea" title="installer.beacon"}
				{fbvFormSection list=true}
					{fbvElement type="checkbox" id="enableBeacon" value="1" checked=$enableBeacon label="installer.beacon.enable"}
				{/fbvFormSection}
			{/fbvFormArea}

			{fbvFormButtons id="installFormSubmit" submitText="common.save" hideCancel=true submitText="installer.installApplication"}
		</form>
	</div>
</div>
{/block}
