{**
 * buttonBar.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Custom modal button bar
 *}

<script type='text/javascript'>
	{literal}$(function() {
		$('{/literal}{$id}{literal}').parent().next('.ui-dialog-buttonpane').hide();
		$('.button').button();
	});{/literal}
</script>

{fbvFormArea id="buttons"}
    {fbvFormSection}
        {fbvLink id="cancelModalButton" label="common.cancel"}
        {fbvButton id="submitModalButton" label=$submitText disabled=$submitDisabled align=$fbvStyles.align.RIGHT}
    {/fbvFormSection}
{/fbvFormArea}

