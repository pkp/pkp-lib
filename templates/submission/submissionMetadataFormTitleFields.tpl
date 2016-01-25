{**
 * submission/submissionMetadataFormTitleFields.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Submission's metadata form title fields. To be included in any form that wants to handle
 * submission metadata.
 *}
{fbvFormArea id="generalInformation" title="submission.submit.titleAndSummary"}
	{fbvFormSection for="title" title="common.prefix" inline="true" size=$fbvStyles.size.SMALL}
		{fbvElement type="text" multilingual=true name="prefix" id="prefix" value=$prefix readonly=$readOnly maxlength="32"}
	{/fbvFormSection}
	{fbvFormSection for="title" title="common.title" inline="true" size=$fbvStyles.size.LARGE required=true}
		{fbvElement type="text" multilingual=true name="title" id="title" value=$title readonly=$readOnly maxlength="255" required=true}
	{/fbvFormSection}
	{fbvFormSection description="common.prefixAndTitle.tip"}{/fbvFormSection}
	{fbvFormSection title="common.subtitle" for="subtitle"}
		{fbvElement type="text" multilingual=true name="subtitle" id="subtitle" value=$subtitle readonly=$readOnly}
	{/fbvFormSection}
	{fbvFormSection description="common.subtitle.tip"}{/fbvFormSection}
	{fbvFormSection title="common.abstract" for="abstract" required=$abstractsRequired}
		{fbvElement type="textarea" multilingual=true name="abstract" id="abstract" value=$abstract rich="extended" readonly=$readOnly}
	{/fbvFormSection}
{/fbvFormArea}
