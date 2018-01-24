{**
 * templates/submission/submissionMetadataFormFields.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Submission's metadata form fields. To be included in any form that wants to handle
 * submission metadata.
 *}

{if $citationsEnabled && array_intersect(array(ROLE_ID_REVIEWER, ROLE_ID_AUTHOR), (array)$userRoles)}
	{assign var=citationsEnabled value=true}
{else}
	{assign var=citationsEnabled value=false}
{/if}
{if $coverageEnabled || $typeEnabled || $sourceEnabled || $rightsEnabled ||
		$languagesEnabled || $subjectsEnabled || $keywordsEnabled || $agenciesEnabled || $citationsEnabled || $disciplinesEnabled}
	{fbvFormSection title="submission.metadata"}
		<p class="description">{translate key="submission.metadataDescription"}</p>
	{/fbvFormSection}
{/if}
{if $coverageEnabled || $typeEnabled || $sourceEnabled || $rightsEnabled}
	{fbvFormArea id="additionalDublinCore"}
		{if $coverageEnabled}
			{fbvFormSection title="submission.coverage" for="coverage"}
				{fbvElement type="text" multilingual=true name="coverage" id="coverage" value=$coverage maxlength="255" readonly=$readOnly required=false required=$coverageRequired}
			{/fbvFormSection}
		{/if}
		{if $typeEnabled}
			{fbvFormSection for="type" title="common.type"}
				{fbvElement type="text" label="submission.type.tip" multilingual=true name="type" id="type" value=$type maxlength="255" readonly=$readOnly required=$typeRequired}
			{/fbvFormSection}
		{/if}
		{if $sourceEnabled}
			{fbvFormSection label="submission.source" for="source"}
				{fbvElement type="text" label="submission.source.tip" multilingual=true name="source" id="source" value=$source maxlength="255" readonly=$readOnly required=$sourceRequired}
			{/fbvFormSection}
		{/if}
		{if $rightsEnabled}
			{fbvFormSection label="submission.rights" for="rights"}
				{fbvElement type="text" label="submission.rights.tip" multilingual=true name="rights" id="rights" value=$rights maxlength="255" readonly=$readOnly required=$rightsRequired}
			{/fbvFormSection}
		{/if}
	{/fbvFormArea}
{/if}

{if $languagesEnabled || $subjectsEnabled || $keywordsEnabled || $agenciesEnabled || $citationsEnabled || $disciplinesEnabled}
	{fbvFormArea id="tagitFields" title="submission.submit.metadataForm"}
		{if $languagesEnabled}
			{$languagesField}
		{/if}
		{if $subjectsEnabled}
			{fbvFormSection label="common.subjects"}
				{fbvElement type="keyword" id="subjects" multilingual=true current=$subjects disabled=$readOnly required=$subjectsRequired}
			{/fbvFormSection}
		{/if}
		{if $disciplinesEnabled}
			{fbvFormSection label="search.discipline"}
				{fbvElement type="keyword" id="disciplines" multilingual=true current=$disciplines disabled=$readOnly required=$disciplinesRequired}
			{/fbvFormSection}
		{/if}
		{if $keywordsEnabled}
			{fbvFormSection label="common.keywords"}
				{fbvElement type="keyword" id="keywords" multilingual=true current=$keywords disabled=$readOnly required=$keywordsRequired}
			{/fbvFormSection}
		{/if}
		{if $agenciesEnabled}
			{fbvFormSection label="submission.supportingAgencies"}
				{fbvElement type="keyword" id="agencies" multilingual=true current=$agencies disabled=$readOnly required=$agenciesRequired}
			{/fbvFormSection}
		{/if}
		{if $citationsEnabled}
			{fbvFormSection label="submission.citations"}
				{fbvElement type="textarea" id="citations" value=$citations disabled=$readOnly required=$citationsRequired}
			{/fbvFormSection}
		{/if}
	{/fbvFormArea}
{/if}

{call_hook name="Templates::Submission::SubmissionMetadataForm::AdditionalMetadata"}
