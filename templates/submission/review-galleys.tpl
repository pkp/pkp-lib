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
        <h3 id="review{$step.id}">
            {$step.reviewName}
        </h3>
        <pkp-button
            aria-describedby="review{$step.id}"
            class="submissionWizard__reviewPanel__edit"
            @click="openStep('{$step.id}')"
        >
            {translate key="common.edit"}
        </pkp-button>
    </div>
    <div
        class="
            submissionWizard__reviewPanel__body
            submissionWizard__reviewPanel__body--{$step.id}
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
        <notification v-if="!errors.files && !galleys.length" type="warning" class="submissionWizard__reviewEmptyWarning">
            <icon icon="exclamation-triangle" :inline="true"></icon>
            {translate key="author.submit.noFiles"}
        </notification>
        <ul v-if="galleys.length" class="submissionWizard__reviewPanel__list">
            <li
                v-for="galley in galleys"
                :key="galley.id"
                class="submissionWizard__reviewPanel__item__value"
            >
                <a
                    :href="galley.urlRemote ? galley.urlRemote : (galley.file ? galley.file.url : '')"
                    class="submissionWizard__reviewPanel__fileLink"
                >
                    <file
                        :document-type="galley.file ? galley.file.documentType : '{\PKP\file\FileManager::DOCUMENT_TYPE_URL}'"
                        :name="galley.label"
                    ></file>
                </a>
                <span
                    v-if="galley.file"
                    class="submissionWizard__reviewPanel__list__actions"
                >
                    <badge :is-primary="!galley.file.genreIsSupplementary">
                        {{ localize(galley.file.genreName) }}
                    </badge>
                </span>
            </li>
        </ul>
    </div>
</div>