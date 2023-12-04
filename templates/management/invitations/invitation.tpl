{**
 * templates/management/access.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief The users, roles and site access settings page.
 *
 * @hook Template::Settings::access []
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
    <div class="submissionWizard">
        <h1 class="app__pageHeading" ref="pageTitle">
            {{ pageTitle }}
        </h1>

{*    <h1 class="app__pageHeading">*}
{*        {translate key="navigation.invitation"}*}
{*    </h1>*}
        <steps
            class="submissionWizard__steps"
            v-if="steps.length"
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
                <panel class="decision__stepPanel">
                    <panel-section class="decision__stepHeader">
                        <h2>{{ step.name }}</h2>
                        <p>{{ step.description }}</p>
                    </panel-section>
                    <template v-if="step.type === 'form'">
                        <panel-section v-for="section in step.sections" :key="section.id">
                            <pkp-form
                                    v-if="section.type === 'form'"
                                    v-bind="section.form"
                                    ref="autosaveForms"
                                    class="submissionWizard__stepForm"
                                    @set="updateAutosaveForm"
                            ></pkp-form>
                        </panel-section>
                    </template>
                    <template v-else-if="step.type === 'email'">
                        <panel-section v-for="section in step.sections" :key="section.id">
                            {capture assign="attachedFilesLabel"}{translate key="common.attachedFiles"}{/capture}
                            {capture assign="bccLabel"}{translate key="email.bcc"}{/capture}
                            {capture assign="ccLabel"}{translate key="email.cc"}{/capture}
                            {capture assign="subjectLabel"}{translate key="email.subject"}{/capture}
                            {capture assign="switchTolabel"}{translate key="common.switchTo"}{/capture}
                            {capture assign="recipientsLabel"}{translate key="email.to"}{/capture}
                            <composer
                                    add-c-c-label="{translate|escape key="common.addCCBCC"}"
                                    attach-files-label="{translate|escape key="common.attachFiles"}"
                                    attached-files-label="{translate|escape key="semicolon" label=$attachedFilesLabel}"
                                    :attachers="section.email.attachers"
                                    :attachments="section.email.attachments"
                                    :bcc="section.email.bcc"
                                    bcc-label="{translate|escape key="semicolon" label=$bccLabel}"
                                    :body="section.email.body"
                                    body-label="{translate|escape key="stageParticipants.notify.message"}"
                                    :can-change-recipients="section.email.canChangeRecipients"
                                    :cc="section.email.cc"
                                    cc-label="{translate|escape key="semicolon" label=$ccLabel}"
                                    confirm-switch-locale-label="{translate|escape key="email.confirmSwitchLocale"}"
                                    deselect-label="{translate|escape key="common.deselect"}"
                                    :email-templates="section.email.emailTemplates"
                                    :email-templates-api-url="emailTemplatesApiUrl"
                                    :errors="section.email.errors"
                                    find-template-label="{translate|escape key="common.findTemplate"}"
                                    :id="section.email.id"
                                    :initial-template-key="section.email.initialTemplateKey"
                                    insert-label="{translate|escape key="common.insert"}"
                                    insert-modal-label="{translate|escape key="common.insertContent"}"
                                    insert-content-label="{translate|escape key="common.content"}"
                                    insert-search-label="{translate|escape key="common.insertContentSearch"}"
                                    load-template-label="{translate|escape key="common.emailTemplates"}"
                                    :locale="section.email.locale"
                                    :locales="section.email.locales"
                                    more-search-results-label="{translate|escape key="common.numberedMore"}"
                                    :recipient-options="section.email.recipientOptions"
                                    :recipients="section.email.recipients"
                                    recipients-label="{translate|escape key="semicolon" label=$recipientsLabel}"
                                    remove-item-label="{translate|escape key="common.removeItem"}"
                                    searching-label="{translate|escape key="common.searching"}"
                                    search-results-label="{translate|escape key="search.searchResults"}"
                                    :subject="section.email.subject"
                                    subject-label="{translate|escape key="semicolon" label=$subjectLabel}"
                                    switch-to-label="{translate|escape key="semicolon" label=$switchTolabel}"
                                    switch-to-named-language-label="{translate|escape key="common.switchToNamedItem"}"
                                    :variables="section.email.variables"
                                    @set="updateStep"
                            ></composer>
                        </panel-section>
                    </template>
                </panel>
            </step>
        </steps>
        <button-row>
            <pkp-button @click="previousStep">Previous</pkp-button>
            <pkp-button @click="nextStep">Next</pkp-button>
        </button-row>
    </div>
{/block}
