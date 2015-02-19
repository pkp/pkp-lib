{**
 * templates/form/checkboxGroup.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form checkboxgroup
 *}

{if $FBV_required}{assign var="required" value="required"}{/if}

{foreach name=checkbox from=$FBV_from item=FBV_label key=FBV_value}
	{if in_array($FBV_value, $FBV_selected)}
		{assign var="FBV_checked" value="checked"}
	{else}
		{assign var="FBV_checked" value=""}
	{/if}
<li{if $FBV_layoutInfo} class="{$FBV_layoutInfo}"{/if}>
	<input type="checkbox" id="{$FBV_id|escape}-{$smarty.foreach.checkbox.index}" name="{$FBV_id|escape}[]"{$FBV_checkboxParams} class="field checkbox{if $FBV_required} required{/if}"{if $FBV_checked} checked="checked"{/if}{if $FBV_validation}{/if} value="{$FBV_value|escape}"{if $FBV_disabled} disabled="disabled"{/if}/>
	{if $FBV_label}<label for="{$FBV_id|escape}-{$smarty.foreach.checkbox.index}" class="choice">{if $FBV_translate}{translate key=$FBV_label}{else}{$FBV_label|strip_unsafe_html}{/if}</label>{/if}
</li>

{/foreach}
