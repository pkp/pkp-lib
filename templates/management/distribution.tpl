{**
 * templates/management/distribution.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * The distribution settings page.
 *}
{include file="common/header.tpl" pageTitle="manager.distribution.title"}

{assign var="uuid" value=""|uniqid|escape}
<div id="settings-context-{$uuid}">
	<tabs>
		<tab id="permissions" name="{translate key="settings.libraryFiles.category.permissions"}">
			{help file="settings" section="distribution" class="pkp_help_tab"}
			<tabs :options="{ useUrlFragment: false}">
				<tab name="{translate key="manager.distribution.access"}">
					<pkp-form
						v-bind="forms.{$smarty.const.FORM_ACCESS}"
						@set-fields="setFormFields"
						@set-errors="setFormErrors"
						@set-visible-locales="setFormVisibleLocales"
					/>
				</tab>
				<tab name="{translate key="submission.license"}">
					<license-form
						v-bind="forms.{$smarty.const.FORM_LICENSE}"
						@set-fields="setFormFields"
						@set-errors="setFormErrors"
						@set-visible-locales="setFormVisibleLocales"
					/>
				</tab>
				{call_hook name="Template::Settings::distribution::permissions"}
			</tabs>
		</tab>
		<tab id="indexing" name="{translate key="manager.setup.searchEngineIndexing"}">
			{help file="settings" section="distribution" class="pkp_help_tab"}
			<pkp-form
				v-bind="forms.{$smarty.const.FORM_SEARCH_INDEXING}"
				@set-fields="setFormFields"
				@set-errors="setFormErrors"
				@set-visible-locales="setFormVisibleLocales"
			/>
			{call_hook name="Template::Settings::distribution::indexing"}
		</tab>
		{call_hook name="Template::Settings::distribution"}
	</tabs>
</div>
<script type="text/javascript">
	pkp.registry.init('settings-context-{$uuid}', 'Container', {$settingsData|json_encode});
</script>

{include file="common/footer.tpl"}
