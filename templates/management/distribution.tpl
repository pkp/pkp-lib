{**
 * templates/management/distribution.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The distribution settings page.
 *
 * @hook Template::Settings::distribution []
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.distribution.title"}
	</h1>

	{if $currentContext->getData('disableSubmissions')}
		<notification>
			{translate key="manager.setup.disableSubmissions.notAccepting"}
		</notification>
	{/if}

	<tabs :track-history="true">
		<tab id="license" label="{translate key="submission.license"}">
			<pkp-form
				v-bind="components.{PKP\components\forms\context\PKPLicenseForm::FORM_LICENSE}"
				@set="set"
			/>
		</tab>
		<tab id="dois" label="{translate key="manager.dois.title"}">
			<tabs :is-side-tabs="true" :track-history="true">
				<tab id="doisSetup" label="{translate key="manager.setup.dois.setup"}">
				<doi-setup-settings-form
						v-bind="components.{PKP\components\forms\context\PKPDoiSetupSettingsForm::FORM_DOI_SETUP_SETTINGS}"
						@set="set"
				/>
				</tab>
				<tab id="doisRegistration" label="{translate key="manager.setup.dois.registration"}">
					<doi-registration-settings-form
						v-bind="components.{PKP\components\forms\context\PKPDoiRegistrationSettingsForm::FORM_DOI_REGISTRATION_SETTINGS}"
						@set="set"
					/>
				</tab>
			</tabs>
		</tab>
		<tab id="indexing" label="{translate key="manager.setup.searchEngineIndexing"}">
			<pkp-form
				v-bind="components.{PKP\components\forms\context\PKPSearchIndexingForm::FORM_SEARCH_INDEXING}"
				@set="set"
			/>
		</tab>
		<tab id="payments" label="{translate key="manager.paymentMethod"}">
			<pkp-form
				v-bind="components.{PKP\components\forms\context\PKPPaymentSettingsForm::FORM_PAYMENT_SETTINGS}"
				@set="set"
			/>
		</tab>
		{if $displayStatisticsTab}
		<tab id="statistics" label="{translate key="manager.setup.statistics"}">
			<pkp-form
				v-bind="components.{PKP\components\forms\context\PKPContextStatisticsForm::FORM_CONTEXT_STATISTICS}"
				@set="set"
			/>
		</tab>
		{/if}
		{call_hook name="Template::Settings::distribution"}
	</tabs>
{/block}
