{**
 * templates/form/formButtons.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form button bar
 * Parameters:
 * 	FBV_hideCancel bool hides the cancel button completely.
 * 	FBV_cancelAction LinkAction to be executed when the cancel button is pressed.
 * 	FBV_cancelUrl string A url to redirect to when cancel is pressed.
 * 	FBV_cancelText string The label to go on the cancel button
 * 	FBV_confirmSubmit string Text to be used in a confirmation modal before submiting the form.
 * 	FBV_submitText string The label to go on the submit button.
 * 	FBV_submitDisabled bool disables the submit button.
 *}

{fbvFormSection class="formButtons"}
	{if !$FBV_hideCancel}
		{if $FBV_cancelAction}
			{include file="linkAction/buttonGenericLinkAction.tpl"
					buttonSelector="#cancelFormButton"
					action=$FBV_cancelAction}
		{elseif $FBV_cancelUrl}
			{include file="linkAction/buttonRedirectLinkAction.tpl"
					buttonSelector="#cancelFormButton"
					cancelUrl=$FBV_cancelUrl}
		{/if}
		{if $FBV_formReset}{assign var="cancelButton" value="resetFormButton"}{else}{assign var="cancelButton" value="cancelFormButton"}{/if}
		{fbvElement type="link" class=$cancelButton id=$cancelButton label=$FBV_cancelText}
	{/if}

	{* IF we have confirmation dialog text specified, load buttonConfirmationLinkAction for the submit button *}
	{if $FBV_confirmSubmit}
		{include file="linkAction/buttonConfirmationLinkAction.tpl"
				 buttonSelector="#submitFormButton"
				 dialogText="$FBV_confirmSubmit"}
	{/if}
	{fbvElement type="submit" class="submitFormButton" id="submitFormButton" label=$FBV_submitText translate=$FBV_translate disabled=$FBV_submitDisabled}
	<div class="pkp_helpers_progressIndicator"></div>
	<div class="clear"></div>
{/fbvFormSection}
