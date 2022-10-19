{**
 * templates/submission/review-license.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The template in the submission wizard to add the relation to a published version to the review step.
 *}
<div class="submissionWizard__reviewPanel">
    <div class="submissionWizard__reviewPanel__header">
        <h3 id="review-relation">
            {translate key="publication.relation.label"}
        </h3>
        <pkp-button
            aria-describedby="review-relation"
            class="submissionWizard__reviewPanel__edit"
            @click="openStep('{$step.id}')"
        >
            {translate key="common.edit"}
        </pkp-button>
    </div>
    <div
        class="
            submissionWizard__reviewPanel__body
            submissionWizard__reviewPanel__body--relation
        "
    >
        <div class="submissionWizard__reviewPanel__item">
            <template v-if="publication.relationStatus === {\APP\publication\Publication::PUBLICATION_RELATION_PUBLISHED}">
                <template v-if="publication.vorDoi">
                    <span v-html="replaceLocaleParams(i18nRelationWithLink, {ldelim}vorDoi: publication.vorDoi{rdelim})"></span>
                </template>
                <template v-else>
                    {translate key="publication.relation.published"}
                </template>
            </template>
            <template v-else>
                {translate key="publication.relation.none"}
            </template>
        </div>
    </div>
</div>