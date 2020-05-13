{**
 * templates/management/distribution.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The distribution settings page.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.distribution.title"}
	</h1>

	<tabs>
		<tab id="license" label="{translate key="submission.license"}">
			{help file="settings/distribution-settings" class="pkp_help_tab"}
			<pkp-form
				v-bind="components.{$smarty.const.FORM_LICENSE}"
				@set="set"
			/>
		</tab>
		<tab id="indexing" label="{translate key="manager.setup.searchEngineIndexing"}">
			{help file="settings/distribution-settings" section="indexing" class="pkp_help_tab"}
			<pkp-form
				v-bind="components.{$smarty.const.FORM_SEARCH_INDEXING}"
				@set="set"
			/>
		</tab>
		<tab id="payments" label="{translate key="manager.paymentMethod"}">
			{help file="settings/distribution-settings" section="payments" class="pkp_help_tab"}
			<pkp-form
				v-bind="components.{$smarty.const.FORM_PAYMENT_SETTINGS}"
				@set="set"
			/>
		</tab>
		{call_hook name="Template::Settings::distribution"}
	</tabs>
{/block}