{**
 * templates/submission/form/license.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Include license for submissions.
 *}
{fbvFormSection title="submission.license"}
	{fbvElement type="select" id="licenseUrl" from=$licenseUrlOptions selected=$licenseUrl translate=false disabled=$readOnly size=$fbvStyles.size.MEDIUM}
{/fbvFormSection}
