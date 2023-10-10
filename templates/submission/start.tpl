{**
 * templates/submission/start.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The initial step for a new submission before launching the submission wizard
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading app__pageHeading--center app__pageHeading--spacious">
		{translate key="submission.wizard.title"}
	</h1>

	{if $currentContext->getData('disableSubmissions')}
		<notification>
			{translate key="manager.setup.disableSubmissions.notAccepting"}
		</notification>
	{else}
		<panel>
			<panel-section>
				<start-submission-form
					class="startSubmissionPage__form"
					v-bind="form"
					@set="updateForm"
				></start-submission-form>
			</panel-section>
		</panel>
	{/if}
{/block}
