{**
 * templates/submission/submissionMetadataFormTitleFields.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Submission's metadata form title fields. To be included in any form that wants to handle
 * submission metadata.
 *}
<div class="pkp_helpers_clear">
	{fbvFormSection for="title" title="common.prefix" inline="true" size=$fbvStyles.size.SMALL}
		{fbvElement label="common.prefixAndTitle.tip" type="text" multilingual=true name="prefix" id="prefix" value=$prefix readonly=$readOnly maxlength="32"}
	{/fbvFormSection}
</div>	
{fbvFormSection title="common.title" for="title"}
	{fbvElement type="textarea" multilingual=true name="title" id="title" value=$title height=$fbvStyles.height.ONELINE rich="oneline" rows="3" readonly=$readOnly}
{/fbvFormSection}
{fbvFormSection title="common.subtitle" for="subtitle"}
	{fbvElement type="textarea" multilingual=true name="subtitle" id="subtitle" value=$subtitle height=$fbvStyles.height.ONELINE rich="oneline" rows="3" readonly=$readOnly}
{/fbvFormSection}
{fbvFormSection title="common.abstract" for="abstract" required=$abstractsRequired}
	{fbvElement type="textarea" multilingual=true name="abstract" id="abstract" value=$abstract rich="extended" readonly=$readOnly}
{/fbvFormSection}
