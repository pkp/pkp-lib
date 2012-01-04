{**
 * element.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form element wrapper
 *}

<span{if $FBV_measureInfo} class="{$FBV_measureInfo}"{/if}>
	{$FBV_content}
	{if $FBV_label}{if $FBV_required}{fieldLabel name=$FBV_id key=$FBV_label required="true"}{else}{fieldLabel name=$FBV_id key=$FBV_label}{/if}{/if}
</span>
