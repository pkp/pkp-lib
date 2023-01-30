{**
 * templates/submission/review-editors-step.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The template in the submission wizard when reviewing the For the Editors step.
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
            {if in_array($currentContext->getData('keywords'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {include file="/submission/review-publication-field.tpl" prop="keywords" inLocale=$localeKey name="{translate key="common.keywords"}" type="array"}
            {/if}
            {if in_array($currentContext->getData('subjects'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {include file="/submission/review-publication-field.tpl" prop="subjects" inLocale=$localeKey name="{translate key="common.subjects"}" type="array"}
            {/if}
            {if in_array($currentContext->getData('disciplines'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {include file="/submission/review-publication-field.tpl" prop="disciplines" inLocale=$localeKey name="{translate key="search.discipline"}" type="array"}
            {/if}
            {if in_array($currentContext->getData('languages'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {include file="/submission/review-publication-field.tpl" prop="languages" inLocale=$localeKey name="{translate key="common.languages"}" type="array"}
            {/if}
            {if in_array($currentContext->getData('agencies'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {include file="/submission/review-publication-field.tpl" prop="supportingAgencies" inLocale=$localeKey name="{translate key="submission.supportingAgencies"}" type="array"}
            {/if}
            {if in_array($currentContext->getData('coverage'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {include file="/submission/review-publication-field.tpl" prop="coverage" inLocale=$localeKey name="{translate key="manager.setup.metadata.coverage"}" type="string"}
            {/if}
            {if in_array($currentContext->getData('rights'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {include file="/submission/review-publication-field.tpl" prop="rights" inLocale=$localeKey name="{translate key="submission.rights"}" type="string"}
            {/if}
            {if in_array($currentContext->getData('source'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {include file="/submission/review-publication-field.tpl" prop="source" inLocale=$localeKey name="{translate key="common.source"}" type="string"}
            {/if}
            {if in_array($currentContext->getData('type'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {include file="/submission/review-publication-field.tpl" prop="type" inLocale=$localeKey name="{translate key="common.type"}" type="string"}
            {/if}
            {if in_array($currentContext->getData('dataAvailability'), [$currentContext::METADATA_REQUEST, $currentContext::METADATA_REQUIRE])}
                {include file="/submission/review-publication-field.tpl" prop="dataAvailability" inLocale=$localeKey name="{translate key="submission.dataAvailability"}" type="html"}
            {/if}
            {if $isCategoriesEnabled && $localeKey === $submission->getData('locale')}
                <div class="submissionWizard__reviewPanel__item">
                    <h4 class="submissionWizard__reviewPanel__item__header">
                        {translate key="submission.submit.placement.categories"}
                    </h4>
                    <ul
                        v-if="currentCategoryTitles.length"
                        class="submissionWizard__reviewPanel__item__value"
                    >
                        <li
                            v-for="currentCategoryTitle in currentCategoryTitles"
                            :key="currentCategoryTitle"
                        >
                            {{ currentCategoryTitle }}
                        </li>
                    </ul>
                    <div
                        v-else
                        class="submissionWizard__reviewPanel__item__value"
                    >
                        {translate key="common.noneSelected"}
                    </div>
                </div>
                <div class="submissionWizard__reviewPanel__item">
                    <h4 class="submissionWizard__reviewPanel__item__header">
                        {translate key="submission.submit.coverNote"}
                    </h4>
                    <div
                        v-if="submission.commentsForTheEditors"
                        class="submissionWizard__reviewPanel__item__value"
                        v-html="submission.commentsForTheEditors"
                    ></div>
                    <div v-else class="submissionWizard__reviewPanel__item__value">
                        {translate key="common.none"}
                    </div>
                </div>
            {/if}
        </div>
    </div>
{/foreach}