{**
 * templates/submission/cancelled.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The page shown to the user when they cancel their submission.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
    <h1 class="app__pageHeading app__pageHeading--center app__pageHeading--spacious">
        {translate key="submission.wizard.submissionCancelled"}
    </h1>

    <div class="app__contentPanel">
        <p>{translate key="submission.wizard.submissionCancelled.description"}</p>
        <p class="pt-2">{translate key="submission.submit.whatNext.forNow"}</p>
        <ul role="list">
            <li><a href={url page="submission"}>{translate key="submission.submit.whatNext.create"}</a></li>
            <li><a href={url page="submissions"}>{translate key="submission.submit.whatNext.return"}</a></li>
        </ul>
    </div>

{/block}
