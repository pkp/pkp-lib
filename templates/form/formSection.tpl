{**
 * templates/form/formSection.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form section.
 *}


{if $FBV_label}<label>{translate key=$FBV_label|escape}</label>{/if}
{if $FBV_description}<span><label class="sub_label">{translate key=$FBV_description}</label></span>{/if}
<{if $FBV_listSection}ul{else}div{/if} class="{if $FBV_listSection}checkbox_and_radiobutton{/if} {$FBV_class|escape} {$FBV_layoutInfo|escape}">
	{if $FBV_title}<label {if $FBV_labelFor} for="{$FBV_labelFor|escape}"{/if}>{translate key=$FBV_title}{if $FBV_required}<span class="req">*</span>{/if}</label>{/if}
		{foreach from=$FBV_sectionErrors item=FBV_error}
			<p class="error">{$FBV_error|escape}</p>
		{/foreach}

		{$FBV_content}
</{if $FBV_listSection}ul{else}div{/if}>

