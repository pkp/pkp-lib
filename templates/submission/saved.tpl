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
		{translate key="submission.wizard.saved"}
	</h1>

    <div class="app__contentPanel">
        <p>{translate key="submission.wizard.saved.description"}</p>
        <p>
            <a href="{$submissionWizardUrl}">
                {$submission->getCurrentPublication()->getShortAuthorString()|escape}
                —
                {$submission->getCurrentPublication()->getLocalizedFullTitle(null, 'html')|strip_unsafe_html}
            </a>
        </p>
        <p>{translate key="submission.wizard.saved.emailConfirmation" email=$email|escape}</p>
    </div>
{/block}
