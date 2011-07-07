{**
 * templates/form/formButtons.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form button bar
 *}

{fbvFormSection id=$FBV_id class="formButtons"}
	{if !$FBV_hideCancel}
		{fbvElement type="link" class="cancelFormButton" id="cancelFormButton" label=$FBV_cancelText}
	{/if}

	{* IF we have confirmation dialog text specified, load buttonConfirmationLinkAction for the submit button *}
	{if $FBV_confirmSubmit}
		{include file="linkAction/buttonConfirmationLinkAction.tpl"
				 buttonSelector="#submitFormButton"
				 dialogText="$FBV_confirmSubmit"}
	{/if}
	{fbvElement type="submit" class="submitFormButton" id="submitFormButton" label=$FBV_submitText disabled=$FBV_submitDisabled}
	<div class="clear"></div>
{/fbvFormSection}
