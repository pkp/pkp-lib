{**
 * textInput.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form text input
 *}

<input type="{if $FBV_isPassword}password{else}text{/if}" {$FBV_textInputParams} class="field text{if $FBV_sizeInfo} {$FBV_sizeInfo|escape}{/if}{if $FBV_validation} {$FBV_validation}{/if}"{if $FBV_disabled} disabled="disabled"{/if}/>
