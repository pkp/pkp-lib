{**
 * templates/submission/review-files-step.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The template in the submission wizard when reviewing the upload files step.
 *}
<div class="submissionWizard__reviewPanel">
    <div class="submissionWizard__reviewPanel__header">
        <h3 id="review{$step.id|escape}">
            {$step.reviewName|escape}
        </h3>
        <pkp-button
            aria-describedby="review{$step.id|escape}"
            class="submissionWizard__reviewPanel__edit"
            @click="openStep('{$step.id|escape}')"
        >
            {translate key="common.edit"}
        </pkp-button>
    </div>
    <div
        class="
            submissionWizard__reviewPanel__body
            submissionWizard__reviewPanel__body--{$step.id|escape}
        "
    >
        <notification
            v-for="(error, i) in errors.files"
            :key="i"
            type="warning"
            class="submissionWizard__reviewEmptyWarning"
        >
            <icon icon="exclamation-triangle" :inline="true"></icon>
            {{ error }}
        </notification>
        <ul class="submissionWizard__reviewPanel__list">
            <li
                v-for="file in components.submissionFiles.items"
                :key="file.id"
                class="submissionWizard__reviewPanel__item__value"
            >
                <a :href="file.url" class="submissionWizard__reviewPanel__fileLink">
                    <file
                        :document-type="file.documentType"
                        :name="localize(file.name)"
                    ></file>
                </a>
                <span
                    class="submissionWizard__reviewPanel__list__actions"
                >
                    <badge v-if="file.genreId" :is-primary="!file.genreIsSupplementary">
                        {{ localize(file.genreName) }}
                    </badge>
                </span>
            </li>
        </ul>
    </div>
</div>