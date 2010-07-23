{**
 * citationFormErrorsAndComparison.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * A part of the citation form that will be refreshed
 * whenever the user changes one of the fields (by manual
 * edit or via a database query). Can be used stand-alone
 * or when refreshing the form as a whole.
 *}
<div id="citationFormErrorsAndComparison">
	{include file="common/formErrors.tpl"}

	{* We have two versions of the raw citation - one editable and the
	   other with mark-up for comparison. We use JS to switch between the
	   two on user demand. *}
	<div id="editableRawCitation">
		<div class="label">{fieldLabel name="rawCitation" key="submission.citations.grid.rawCitation"}</div>
		<div class="value">{fbvElement type="textarea" name="rawCitation" id="rawCitation" size=$fbvStyles.size.LARGE value=$rawCitation}</div>
	</div>
	<div id="rawCitationWithMarkup">
		<div class="label">{translate key="submission.citations.grid.rawCitation"}</div>
		<div class="value">{$rawCitationWithMarkup}</div>
	</div>
	<div id="generatedCitationWithMarkup">
		<div class="label">{translate key="submission.citations.grid.generatedCitation"}</div>
		<div class="value">{$generatedCitationWithMarkup}</div>
	</div>
</div>
