{**
 * templates/submission/review-details-step.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The template in the submission wizard when reviewing the details step.
 *}
{foreach from=$locales item=$locale key=$localeKey}
    <div class="submissionWizard__reviewPanel">
        <div class="submissionWizard__reviewPanel__header">
            <h3 id="review{$step.id|escape}">
                {if count($locales) > 1}
                    {translate key="common.withParenthesis" item=$step.reviewName|escape inParenthesis=$locale}
                {else}
                    {$step.reviewName|escape}
                {/if}
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
            {include file="/submission/review-publication-field.tpl" prop="title" inLocale=$localeKey name="{translate key="common.title"}" type="html"}
            {if in_array($currentContext->getData('keywords'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {include file="/submission/review-publication-field.tpl" prop="keywords" inLocale=$localeKey name="{translate key="common.keywords"}" type="array"}
            {/if}
            {include file="/submission/review-publication-field.tpl" prop="abstract" inLocale=$localeKey name="{translate key="common.abstract"}" type="html"}
            {if in_array($currentContext->getData('citations'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {if $localeKey === $submission->getData('locale')}
                    <div class="submissionWizard__reviewPanel__item">
                        <template v-if="errors.citationsRaw">
                            <notification
                                v-for="(error, i) in errors.citationsRaw"
                                :key="i"
                                type="warning"
                            >
                                <icon icon="exclamation-triangle"></icon>
                                {{ error }}
                            </notification>
                        </template>
                        <h4 class="submissionWizard__reviewPanel__item__header">
                            {translate key="submission.citations"}
                        </h4>
                        <div class="submissionWizard__reviewPanel__item__value">
                            <template v-if="!publication.citationsRaw">
                                {translate key="common.noneProvided"}
                            </template>
                            <div
                                v-else
                                v-for="(citation, index) in publication.citationsRaw.trim().split(/(?:\r\n|\r|\n)/g).filter(c => c)"
                                :key="index"
                                class="submissionWizard__reviewPanel__citation"
                            >
                                {{ citation }}
                            </div>
                        </div>
                    </div>
                {/if}
            {/if}
        </div>
    </div>
{/foreach}