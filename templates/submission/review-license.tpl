{**
 * templates/submission/review-license.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The template in the submission wizard to add the license to the review step.
 *}
<div class="submissionWizard__reviewPanel">
    <div class="submissionWizard__reviewPanel__header">
        <h3 id="review-license">
            {translate key="submission.license"}
        </h3>
        <pkp-button
            aria-describedby="review-license"
            class="submissionWizard__reviewPanel__edit"
            @click="openStep('{$step.id}')"
        >
            {translate key="common.edit"}
        </pkp-button>
    </div>
    <div
        class="
            submissionWizard__reviewPanel__body
            submissionWizard__reviewPanel__body--license
        "
    >
        <div class="submissionWizard__reviewPanel__item">
            <a v-if="publication.licenseUrl" :href="publication.licenseUrl">
                <template v-if="licenses[publication.licenseUrl]">
                    {{ licenses[publication.licenseUrl] }}
                </template>
                <template v-else>
                    {{ publication.licenseUrl }}
                </template>
            </a>
            <template v-else>
                {translate key="submission.licenseUrl.missing"}
            </template>
        </div>
    </div>
</div>
