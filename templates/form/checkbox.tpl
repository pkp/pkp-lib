{**
 * checkbox.tpl
 *
 * Copyright (c) 2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form checkbox
 *}

<input type="checkbox" id="{$FBV_id|escape}" {$FBV_checkboxParams} class="field checkbox"{if $FBV_checked} checked="checked"{/if}{if $FBV_disabled} disabled="disabled"{/if}/>
{if $FBV_label}<label for="{$FBV_id|escape}" class="choice">{if $FBV_translate}{translate key=$FBV_label}{else}{$FBV_label|escape}{/if}</label>{/if}
