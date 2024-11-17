{**
 * templates/form/formButtons.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
 * 	FBV_modalStyle string The modal state/style that should be used.
 *}

{fbvFormSection class="formButtons form_buttons"}

	{* Loading indicator *}
	<span class="pkp_spinner"></span>

	{* Cancel button (if any) *}
	{if !$FBV_hideCancel}
		{assign var=cancelButtonId value="cancelFormButton"|concat:"-"|uniqid}
		{if $FBV_cancelAction}
			{include file="linkAction/buttonGenericLinkAction.tpl"
					buttonSelector="#"|concat:$cancelButtonId
					action=$FBV_cancelAction}
		{elseif $FBV_cancelUrl}
			{include file="linkAction/buttonRedirectLinkAction.tpl"
					buttonSelector="#"|concat:$cancelButtonId
					cancelUrl=$FBV_cancelUrl
					cancelUrlTarget=$FBV_cancelUrlTarget}
		{/if}
		<a href="#" id="{$cancelButtonId}" class="cancelButton">{translate key=$FBV_cancelText}</a>
	{/if}

	{* Submit button *}
	{assign var=submitButtonId value="submitFormButton"|concat:"-"|uniqid}

	{* IF we have confirmation dialog text specified, load buttonConfirmationLinkAction for the submit button *}
	{if $FBV_confirmSubmit}
		{include file="linkAction/buttonConfirmationLinkAction.tpl"
				buttonSelector="#"|concat:$submitButtonId
				dialogText="$FBV_confirmSubmit"
				modalStyle="$FBV_modalStyle"}
	{/if}

	{fbvElement type="submit" class="{if $FBV_saveText}pkp_button_primary{/if} submitFormButton" name="submitFormButton" id=$submitButtonId label=$FBV_submitText translate=$FBV_translate disabled=$FBV_submitDisabled}

	{* Save button *}
	{if $FBV_saveText}
		{assign var=saveButtonId value="saveFormButton"|concat:"-"|uniqid}
		{fbvElement type="submit" class="saveFormButton" name="saveFormButton" id=$saveButtonId label=$FBV_saveText disabled=$FBV_submitDisabled}
	{/if}
{/fbvFormSection}
