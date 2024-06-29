{**
 * templates/management/workflow.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief The workflow settings page.
 *
 * @hook Template::Settings::workflow::submission []
 * @hook Template::Settings::workflow::review []
 * @hook Template::Settings::workflow::emails []
 * @hook Template::Settings::workflow []
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.workflow.title"}
	</h1>

	{if $currentContext->getData('disableSubmissions')}
		<notification>
			{translate key="manager.setup.disableSubmissions.notAccepting"}
		</notification>
	{/if}

	<tabs :track-history="true">
		<tab id="submission" label="{translate key="manager.publication.submissionStage"}">
			{help file="settings/workflow-settings" section="submission" class="pkp_help_tab"}
			<tabs :is-side-tabs="true" :track-history="true">
				<tab id="disableSubmissions" label="{translate key="manager.setup.disableSubmissions"}">
					<pkp-form
						v-bind="components.{PKP\components\forms\context\PKPDisableSubmissionsForm::FORM_DISABLE_SUBMISSIONS}"
						@set="set"
					/>
				</tab>
				<tab id="instructions" label="{translate key="manager.setup.workflow.guidance"}">
					<pkp-form
						v-bind="components.submissionGuidanceSettings"
						@set="set"
					/>
				</tab>
				<tab id="metadata" label="{translate key="submission.informationCenter.metadata"}">
					<pkp-form
						v-bind="components.{PKP\components\forms\context\PKPMetadataSettingsForm::FORM_METADATA_SETTINGS}"
						@set="set"
					/>
				</tab>
				<tab id="components" label="{translate key="grid.genres.title.short"}">
					{capture assign=genresUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.settings.genre.GenreGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="genresGridContainer" url=$genresUrl}
				</tab>
				{call_hook name="Template::Settings::workflow::submission"}
			</tabs>
		</tab>
		{if $hasReviewStage}
			<tab id="review" label="{translate key="manager.publication.reviewStage"}">
				{help file="settings/workflow-settings" section="review" class="pkp_help_tab"}
				<tabs :is-side-tabs="true" :track-history="true">
					<tab id="reviewSetup" label="{translate key="navigation.setup"}">
						<pkp-form
							v-bind="components.{PKP\components\forms\context\PKPReviewSetupForm::FORM_REVIEW_SETUP}"
							@set="set"
						/>
					</tab>
					<tab id="reviewerGuidance" label="{translate key="manager.publication.reviewerGuidance"}">
						<pkp-form
							v-bind="components.{PKP\components\forms\context\PKPReviewGuidanceForm::FORM_REVIEW_GUIDANCE}"
							@set="set"
						/>
					</tab>
					<tab id="reviewForms" label="{translate key="manager.reviewForms"}">
						{capture assign=reviewFormsUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.settings.reviewForms.ReviewFormGridHandler" op="fetchGrid" escape=false}{/capture}
						{load_url_in_div id="reviewFormGridContainer" url=$reviewFormsUrl}
					</tab>
					{call_hook name="Template::Settings::workflow::review"}
				</tabs>
			</tab>
		{/if}
		<tab id="library" label="{translate key="manager.publication.library"}">
			{help file="settings/workflow-settings" section="publisher" class="pkp_help_tab"}
			{capture assign=libraryGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.settings.library.LibraryFileAdminGridHandler" op="fetchGrid" canEdit=true escape=false}{/capture}
			{load_url_in_div id="libraryGridDiv" url=$libraryGridUrl}
		</tab>
		<tab id="emails" label="{translate key="manager.publication.emails"}">
			{help file="settings/workflow-settings" section="emails" class="pkp_help_tab"}
			<pkp-form
				v-bind="components.emailSetup"
				@set="set"
			/>
			{call_hook name="Template::Settings::workflow::emails"}
		</tab>
		{call_hook name="Template::Settings::workflow"}
	</tabs>
{/block}
