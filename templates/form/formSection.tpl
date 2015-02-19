{**
 * templates/form/formSection.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form section.
 *}


<div {if $FBV_id}id="{$FBV_id|escape}" {/if}class="section {$FBV_class|escape} {$FBV_layoutInfo|escape}">
	{if $FBV_label}<label>{translate key=$FBV_label|escape}</label>{/if}
	{if $FBV_description}<span><label class="sub_label">{if $FBV_translate}{translate key=$FBV_description}{else}{$FBV_description|strip_unsafe_html}{/if}</label></span>{/if}
	{if $FBV_listSection}<ul class="checkbox_and_radiobutton">{/if}
		{if $FBV_title}<label {if $FBV_labelFor} for="{$FBV_labelFor|escape}"{/if}>{translate key=$FBV_title}{if $FBV_required}<span class="req">*</span>{/if}</label>{/if}
			{foreach from=$FBV_sectionErrors item=FBV_error}
				<p class="error">{$FBV_error|escape}</p>
			{/foreach}

			{$FBV_content}
	{if $FBV_listSection}</ul>{/if}
</div>
