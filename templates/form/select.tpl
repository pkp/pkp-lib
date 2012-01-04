{**
 * select.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form select
 *}

<select {$FBV_selectParams} class="field select"{if $FBV_disabled} disabled="disabled"{/if}>
	{if $FBV_defaultValue !== null}<option value="{$FBV_defaultValue|escape}">{$FBV_defaultLabel|escape}</option>{/if}
	{if $FBV_translate}{html_options_translate options=$FBV_from selected=$FBV_selected}{else}{html_options options=$FBV_from selected=$FBV_selected}{/if}
</select>
