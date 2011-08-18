{**
 * templates/form/checkboxGroup.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form checkboxgroup
 *}

{if $FBV_required}{assign var="required" value="required"}{/if}

<span id="{$FBV_name|escape}">

{if $FBV_translate}
	{html_checkboxes_translate class="field checkbox $required" name=$FBV_name|escape validation=$FBV_required|escape options=$FBV_from selected=$FBV_selected}
{else}
	{html_checkboxes class="field checkbox $required" name=$FBV_name|escape validation=$FBV_required|escape options=$FBV_from selected=$FBV_selected}
{/if}
</span>

<span>{$FBV_label_content}</span>
