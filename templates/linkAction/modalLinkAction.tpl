{**
 * modalLinkAction.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Attach a ModalLinkAction handler to an element.
 *
 * Parameters:
 *  action: A ModalLinkAction object.
 *}

{assign var="modal" value=$action->getModal()}

{* Generate the link action's button. *}
{assign var=buttonId value=$id|concat:"-":$action->getId():"-button"|uniqid}
{include file="linkAction/linkActionButton.tpl" action=$action buttonId=$buttonId}

{* Attach the JS modal handler to the button. *}
<script type="text/javascript">
	<!--
	$(function() {ldelim}
		$('#{$buttonId}').pkpHandler(
				'$.pkp.controllers.linkAction.ModalLinkActionHandler',
				{ldelim}
					modalHandler: '{$modal->getJSHandler()}',
					modalOptions: {ldelim}
						{foreach name=modalOptions from=$modal->getLocalizedModalOptions() key=optionName item=optionValue}
							{if $optionValue}{$optionName}: '{$optionValue|escape:javascript}'{if !$smarty.foreach.modalOptions.last},{/if}{/if}
						{/foreach}
					{rdelim}
				{rdelim});
	{rdelim});
	// -->
</script>
