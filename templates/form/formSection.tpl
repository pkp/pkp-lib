{**
 * templates/form/formSection.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form section.
 *}

<{if $FBV_listSection}ul{else}div{/if} class="{if $FBV_listSection}checkbox_and_radiobutton{/if}{if $FBV_class}{$FBV_class|escape}{/if}">
	{if $FBV_title}<label class="desc"{if $FBV_labelFor} for="{$FBV_labelFor|escape}"{/if}>{translate key=$FBV_title}{if $FBV_required}<span class="req">*</span>{/if}</label>{/if}
		{foreach from=$FBV_sectionErrors item=FBV_error}
			<p class="error">{$FBV_error|escape}</p>
		{/foreach}

		{$FBV_content}
</{if $FBV_listSection}ul{else}div{/if}>

