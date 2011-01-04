{**
 * ajaxLinkAction.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Attach an AjaxLinkAction handler to an element.
 *
 * Parameters:
 *  action: An AjaxLinkAction object.
 *}

{* Generate the link action's button. *}
{assign var=buttonId value=$id|concat:"-":$action->getId():"-button"|uniqid}
{include file="linkAction/linkActionButton.tpl" action=$action buttonId=$buttonId}

{* Attach the AJAX handler to the button. *}
<script type="text/javascript">
	<!--
	$(function() {ldelim}
		ajaxAction(
			'{$action->getType()|escape:"javascript"}',
			'#{$action->getActOn()|escape:"javascript"}',
			'#{$buttonId|escape:"javascript"}',
			'{$action->getUrl()|escape:"javascript"}'
		);
	{rdelim});
	// -->
</script>
