{**
 * templates/form/textArea.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form text area
 *}

<textarea name="{$FBV_name|escape}" id="{$FBV_id|escape}" {$FBV_textAreaParams} class="field textarea {$FBV_class} {if $FBV_sizeInfo}{$FBV_sizeInfo}{/if}{if $FBV_validation} {$FBV_validation|escape}{/if}{if $FBV_rich} richContent{/if}"{if $FBV_disabled} disabled="disabled"{/if}>{$FBV_value|escape}</textArea>

{$FBV_label_content}
