{**
 * templates/form/formButtons.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form button bar
 *
 * Parameters:
 *  submitText: The text to be displayed on the submit button.
 *  submitDisabled: Whether to disable the submit button.
 *}

{if !$submitText}
	{assign var="submitText" value="common.ok"}
{/if}
{if !$submitDisabled}
	{assign var="submitDisabled" value=false}
{/if}

{fbvFormArea id="buttons"}
    {fbvFormSection}
        {fbvLink id="cancelFormButton" label="common.cancel"}
        {fbvButton type="submit" id="submitFormButton" label=$submitText disabled=$submitDisabled align=$fbvStyles.align.RIGHT}
    {/fbvFormSection}
{/fbvFormArea}
