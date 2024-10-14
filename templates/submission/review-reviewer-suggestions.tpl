{**
 * templates/submission/review-contributors.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The template in the submission wizard when reviewing the contributors step.
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
        v-if="!submission.reviewerSuggestions?.length ?? 0"
        type="warning"
        class="submissionWizard__reviewEmptyWarning"
    >
         <icon icon="exclamation-triangle" :inline="true"></icon>
         {translate key="submission.wizard.noReviewerSuggestions"}
     </notification>
     <ul
         v-else
         class="submissionWizard__reviewPanel__list"
     >
         <li
             v-for="(error, i) in errors.reviewerSuggestions"
             :key="i"
         >
             <notification type="warning">
                 <icon icon="exclamation-triangle" :inline="true"></icon>
                 {{ error }}
             </notification>
         </li>
         <li
            v-for="reviewerSuggestion in submission.reviewerSuggestions"
            :key="reviewerSuggestion.id"
            class="submissionWizard__reviewPanel__item__value"
         >
            <span class="submissionWizard__reviewPanel__list__name" >
                {{ localize(reviewerSuggestion.fullName) }}
                <badge>{{ reviewerSuggestion.email }}</badge>
            </span>
            <span
                class="submissionWizard__reviewPanel__list__actions"
            >
                <badge>{{ localize(reviewerSuggestion.affiliation) }}</badge>
            </span>
         </li>
         {call_hook name="Template::SubmissionWizard::Section::Review::ReviewerSuggestions" submission=$submission step=$step.id}
     </ul>
 </div>
</div>