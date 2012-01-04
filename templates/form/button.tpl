{**
 * templates/form/button.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form button
 *}

<div{if $FBV_layoutInfo} class="{$FBV_layoutInfo}"{/if}>
	<button class="{$FBV_class} button" type="{$FBV_type|escape}"{if $FBV_disabled} disabled="disabled"{/if} {$FBV_buttonParams}>{translate key=$FBV_label}</button>
</div>