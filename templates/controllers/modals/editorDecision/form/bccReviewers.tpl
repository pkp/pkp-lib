{**
 * templates/controllers/modals/editorDecision/form/bccReviewers.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Checkboxes to define which reviewer should receive a bcc copy of the message.
 *
 *}

{if count($reviewers)}
	{fbvFormSection title="submission.comments.sendToReviewers"}
		<span class="description">{translate key="submission.comments.sendCopyToReviewers"}</span>
		<ul class="checkbox_and_radiobutton">
			{foreach from=$reviewers item="name" key="id"}
				{fbvElement type="checkbox" id="bccReviewers[]" value=$id checked=in_array($id, $selected) label=$name translate=false}
			{/foreach}
		</ul>
	{/fbvFormSection}
{/if}
