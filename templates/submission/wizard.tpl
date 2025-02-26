{**
 * templates/submission/wizard.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The submission wizard screen with all steps to complete for submission.
 *
 * @hook Template::SubmissionWizard::Section::Review [[submission, step], $templateMgr, $output]
 * @hook Template::SubmissionWizard::Section [[submission], $templateMgr, $output]
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
    <div class="submissionWizard">
        <div class="submissionWizard__submissionDetails">
            {{ submission.id }}
            <template v-if="publication.authorsStringShort">
                <span class="app__breadcrumbsSeparator" aria-hidden="true">/</span>
                {{ publication.authorsStringShort }}
            </template>
            <template v-if="localize(publication.title)">
                <span class="app__breadcrumbsSeparator" aria-hidden="true">/</span>
                <span v-strip-unsafe-html="localize(publication.title)">
            </template>
        </div>
        <h1 class="app__pageHeading" ref="pageTitle">
            {{ pageTitle }}
            <pkp-button
                :is-disabled="isDisconnected"
                @click="saveForLater"
            >
                {translate key="reviewer.submission.saveReviewForLater"}
            </pkp-button>
        </h1>
        {if $submittingTo}
            <div id="submission-configuration" class="submissionWizard__submissionConfiguration">
                {$submittingTo}
                <button
                    class="-linkButton"
                    aria-describedby="submission-configuration"
                    @click="openReconfigureModal"
                >
                    {translate key="manager.reviewerSearch.change"}
                </button>
            </div>
        {/if}
        <steps
            class="submissionWizard__steps"
            :current="currentStep.id"
            :started-steps="startedSteps"
            label="{translate key="submission.wizard.completeSteps"}"
            progress-label="{translate key="common.showingSteps"}"
            :scroll-to="$refs.pageTitle"
            show-steps-label="{translate key="common.showAllSteps"}"
            @step:open="openStep"
        >
            <step
                v-for="step in steps"
                :key="step.id"
                :id="step.id"
                :label="step.name"
            >
                <panel>
                    <panel-section v-for="section in step.sections" :key="section.id">
                        <template #header>
                            <h2>{{ section.name }}</h2>
                            <div v-strip-unsafe-html="section.description" />
                        </template>
                        <pkp-form
                            v-if="section.type === 'form'"
                            v-bind="section.form"
                            ref="autosaveForms"
                            class="submissionWizard__stepForm"
                            @set="updateAutosaveForm"
                        ></pkp-form>
                        <submission-files-list-panel
                            v-else-if="section.type === 'files'"
                            v-bind="components.submissionFiles"
                            @set="set"
                        ></submission-files-list-panel>
                        <contributors-list-panel
                            v-else-if="section.type === 'contributors'"
                            v-bind="components.contributors"
                            :items="publication.authors"
                            :publication="publication"
                            @updated:contributors="setContributors"
                            @updated:publication="setPublication"
                        ></contributors-list-panel>
                        <reviewer-suggestions-list-panel
                            v-else-if="section.type === 'reviewerSuggestions'"
                            v-bind="components.reviewerSuggestions"
                            :items="submission.reviewerSuggestions"
                            :submission="submission"
                            :publication="publication"
                            @updated:reviewer-suggestions="setReviewerSuggestion"
                        ></reviewer-suggestions-list-panel>
                        <template v-else-if="section.type === 'review'">
                            <notification
                                v-if="Object.keys(errors).length" type="warning"
                                class="submissionWizard__review_errors"
                            >
                                {translate key="submission.wizard.errors"}
                            </notification>
                            {foreach from=$reviewSteps item=$step}
                                {if $step.reviewTemplate}
                                    {include file=$step.reviewTemplate}
                                {/if}
                                {call_hook name="Template::SubmissionWizard::Section::Review" submission=$submission step=$step.id}
                            {/foreach}
                            <transition name="submissionWizard__reviewLoading">
                                <span
                                    v-if="isAutosaving || isValidating"
                                    role="alert"
                                    class="submissionWizard__loadingReview"
                                >
                                    <spinner></spinner>
                                    {translate key="submission.wizard.validating"}
                                </span>
                            </transition>
                        </template>
                        <pkp-form
                            v-if="section.type === 'confirm'"
                            v-bind="section.form"
                            class="submissionWizard__stepForm"
                            @set="updateForm"
                        ></pkp-form>
                        {call_hook name="Template::SubmissionWizard::Section" submission=$submission}
                    </panel-section>
                </panel>
            </step>
        </steps>

        <button-row class="submissionWizard__footer">
            <template #end>
                <pkp-button
                    v-if="!isOnFirstStep"
                    :is-warnable="true"
                    @click="previousStep"
                >
                    {translate key="common.back"}
                </pkp-button>
                <!--
                    Leave this v-else in so that the slot
                    is never empty.
                -->
                <span v-else></span>
            </template>
            <span
                role="status"
                aria-live="polite"
                class="submissionWizard__lastSaved"
                :class="isDisconnected ? 'submissionWizard__lastSaved--disconnected' : ''"
            >
                <spinner v-if="isAutosaving || isDisconnected"></spinner>
                <template v-if="isAutosaving">
                    {translate key="common.saving"}
                </template>
                <template v-else-if="isDisconnected">
                    {translate key="common.reconnecting"}
                </template>
                <template v-else-if="lastAutosavedMessage">
                    {{ lastAutosavedMessage }}
                </template>
            </span>
            {if $canCancelSubmission}
                <pkp-button  :is-warnable="true" :is-link="true" id='cancelSubmission' @click="cancelSubmission">{translate key="common.cancel"}</pkp-button>
            {/if}
            <pkp-button
                :is-disabled="isDisconnected"
                @click="saveForLater"
            >
                {translate key="reviewer.submission.saveReviewForLater"}
            </pkp-button>
            <pkp-button
                :is-primary="true"
                :is-disabled="isOnLastStep && !canSubmit"
                @click="nextStep"
            >
                <template v-if="isOnLastStep">
                    {translate key="form.submit"}
                </template>
                <template v-else>
                    {translate key="common.continue"}
                </template>
            </pkp-button>
        </button-row>
    </div>
{/block}
