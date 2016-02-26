{**
 * submission/submissionMetadataFormFields.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Submission's metadata form fields. To be included in any form that wants to handle
 * submission metadata.
 *}
{if $coverageEnabled}
	{fbvFormArea id="coverageInformation" title="submission.coverage"}
		{fbvFormSection title="submission.coverage.chron" for="coverageChron" description="submission.coverage.tip"}
			{fbvElement type="text" multilingual=true name="coverageChron" id="coverageChron" value=$coverageChron maxlength="255" readonly=$readOnly}
		{/fbvFormSection}
		{fbvFormSection title="submission.coverage.geo" for="coverageGeo"}
			{fbvElement type="text" multilingual=true name="coverageGeo" id="coverageGeo" value=$coverageGeo maxlength="255" readonly=$readOnly}
		{/fbvFormSection}
		{fbvFormSection title="submission.coverage.sample" for="coverageSample"}
			{fbvElement type="text" multilingual=true name="coverageSample" id="coverageSample" value=$coverageSample maxlength="255" readonly=$readOnly}
		{/fbvFormSection}
	{/fbvFormArea}
{/if}

{if $typeEnabled || $subjectEnabled || $sourceEnabled || $rightsEnabled}
	{fbvFormArea id="additionalDublinCore" title="common.type"}
		{if $typeEnabled}
			{fbvFormSection for="type" title="common.type" description="submission.type.tip"}
				{fbvElement type="text" multilingual=true name="type" id="type" value=$type maxlength="255" readonly=$readOnly}
			{/fbvFormSection}
		{/if}
		{if $subjectEnabled}
			{fbvFormSection label="submission.subjectClass" for="subjectClass" description="submission.subjectClass.tip"}
				{fbvElement type="text" multilingual=true name="subjectClass" id="subjectClass" value=$subjectClass maxlength="255" readonly=$readOnly}
			{/fbvFormSection}
		{/if}

		{if $sourceEnabled}
			{fbvFormSection label="submission.source" for="source" description="submission.source.tip"}
				{fbvElement type="text" multilingual=true name="source" id="source" value=$source maxlength="255" readonly=$readOnly}
			{/fbvFormSection}
		{/if}

		{if $rightsEnabled}
			{fbvFormSection label="submission.rights" for="rights" description="submission.rights.tip"}
				{fbvElement type="text" multilingual=true name="rights" id="rights" value=$rights maxlength="255" readonly=$readOnly}
			{/fbvFormSection}
		{/if}
	{/fbvFormArea}
{/if}

{fbvFormArea id="tagitFields" title="submission.submit.metadataForm"}
	{if $languagesEnabled}
		{fbvFormSection description="submission.submit.metadataForm.tip" title="common.languages"}
			{url|assign:languagesSourceUrl router=$smarty.const.ROUTE_PAGE page="submission" op="fetchChoices" codeList="74"}
			{fbvElement type="keyword" id="languages" subLabelTranslate=true multilingual=true current=$languages source=$languagesSourceUrl disabled=$readOnly}
		{/fbvFormSection}
	{/if}
	{if $subjectEnabled}
		{fbvFormSection label="common.subjects"}
			{fbvElement type="keyword" id="subjects" subLabelTranslate=true multilingual=true current=$subjects disabled=$readOnly}
		{/fbvFormSection}
	{/if}
	{if $disciplineEnabled}
		{fbvFormSection label="search.discipline"}
			{fbvElement type="keyword" id="disciplines" subLabelTranslate=true multilingual=true current=$disciplines disabled=$readOnly}
		{/fbvFormSection}
	{/if}
	{if $keywordsEnabled}
		{fbvFormSection label="common.keywords"}
			{fbvElement type="keyword" id="keyword" subLabelTranslate=true multilingual=true current=$keywords disabled=$readOnly}
		{/fbvFormSection}
	{/if}
	{if $agenciesEnabled}
		{fbvFormSection label="submission.supportingAgencies"}
			{fbvElement type="keyword" id="agencies" multilingual=true subLabelTranslate=true current=$agencies disabled=$readOnly}
		{/fbvFormSection}
	{/if}
	{if $referencesEnabled}
		{fbvFormSection label="submission.citations"}
			{fbvElement type="textarea" id="citations" subLabelTranslate=true value=$citations}
		{/fbvFormSection}
	{/if}
{/fbvFormArea}

{call_hook name="Templates::Submission::SubmissionMetadataForm::AdditionalMetadata"}
