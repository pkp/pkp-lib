{**
 * templates/workflow/submissionIdentification.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Show submission identification component
 *}

<span class="pkpWorkflow__identificationId">
	{{ submission.id }}
</span>
<span class="pkpWorkflow__identificationDivider">
	/
</span>


<span v-if="currentPublication.authorsStringShort" class="pkpWorkflow__identificationAuthor">
	{{ currentPublication.authorsStringShort }}
</span>
<span v-if="currentPublication.authorsStringShort" class="pkpWorkflow__identificationDivider">
	/
</span>


<span  class="pkpWorkflow__identificationTitle">
	{{ localizeSubmission(currentPublication.fullTitle, currentPublication.locale) }}
</span>