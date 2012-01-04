{**
 * radioButton.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form radio button
 *}

<input type="radio" id="{$FBV_id|escape}" {$FBV_radioParams} class="field radio"{if $FBV_checked} checked="checked"{/if}{if $FBV_disabled} disabled="disabled"{/if}/>
{if $FBV_label}<label for="{$FBV_id|escape}" class="choice">{if $FBV_translate}{translate key=$FBV_label}{else}{$FBV_label|escape}{/if}</label>{/if}
