{**
 * templates/form/keywordInput.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Keyword input control
 *}

<div class="keywordInputContainer{if $FBV_layoutInfo} {$FBV_layoutInfo}{/if}"><div>
{if $FBV_multilingual}
	{foreach from=$formLocales key=thisFormLocale item=thisFormLocaleName}
		{literal}
		<script type="text/javascript">
			<!--
			$(document).ready(function(){
				$("#{/literal}{$thisFormLocale|escape}-{$FBV_id|escape:"javascript"}{literal}").tagit({
					{/literal}{if $FBV_availableKeywords}{literal}
						// This is the list of keywords in the system used to populate the autocomplete
						availableTags: [{/literal}{foreach name=existingKeywords from=$FBV_availableKeywords.$thisFormLocale item=FBV_keyword_element}"{$FBV_keyword_element|escape:'javascript'|replace:'+':' '}"{if !$smarty.foreach.existingKeywords.last}, {/if}{/foreach}{literal}],
					{/literal}{/if}
					{if $FBV_currentKeywords}{literal}
						// This is the list of the user's keywords that have already been saved
						currentTags: [{/literal}{foreach name=currentKeywords from=$FBV_currentKeywords.$thisFormLocale item=FBV_keyword_element}"{$FBV_keyword_element|escape:'javascript'|replace:'+':' '}"{if !$smarty.foreach.currentKeywords.last}, {/if}{/foreach}{literal}]{/literal}
					{/if}{literal}
				});
			});
			// -->
		</script>
		{/literal}
	{/foreach}

	<script type="text/javascript">
		$(function() {ldelim}
			$('#{$FBV_id|escape:javascript}-localization-popover-container').pkpHandler(
				'$.pkp.controllers.form.MultilingualInputHandler'
				);
		{rdelim});
		</script>

		<span id="{$FBV_id|escape}-localization-popover-container" class="localization_popover_container pkpTagit">
			<ul class="localization_popover_container localizable {if $formLocale != $currentLocale} locale_{$formLocale|escape}{/if}" id="{$formLocale|escape}-{$FBV_id|escape}"><li></li></ul>
			{if $FBV_label}<span>{if $FBV_required}{fieldLabel name=$FBV_id key=$FBV_label class="sub_label" required="true"}{else}{fieldLabel name=$FBV_id key=$FBV_label class="sub_label"}{/if}</span>{/if}
			<span>
				<div class="localization_popover">
					{foreach from=$formLocales key=thisFormLocale item=thisFormLocaleName}{if $formLocale != $thisFormLocale}
						<ul class="multilingual_extra flag flag_{$thisFormLocale|escape}" id="{$thisFormLocale|escape}-{$FBV_id|escape}"><li></li></ul>
					{/if}{/foreach}
				</div>
			</span>
		</span>
	{else} {* this is not a multilingual keyword field *}
	{literal}
	<script type="text/javascript">
		<!--
		$(document).ready(function(){
			$("#{/literal}{$FBV_id|escape:"javascript"}{literal}").tagit({
				{/literal}{if $FBV_availableKeywords}{literal}
					// This is the list of keywords in the system used to populate the autocomplete
					availableTags: [{/literal}{foreach name=existingKeywords from=$FBV_availableKeywords item=FBV_keyword_element}"{$FBV_keyword_element|escape:'javascript'|replace:'+':' '}"{if !$smarty.foreach.existingKeywords.last}, {/if}{/foreach}{literal}],
				{/literal}{/if}
				{if $FBV_currentKeywords}{literal}
					// This is the list of the user's keywords that have already been saved
					currentTags: [{/literal}{foreach name=currentKeywords from=$FBV_currentKeywords item=FBV_keyword_element}"{$FBV_keyword_element|escape:'javascript'|replace:'+':' '}"{if !$smarty.foreach.currentKeywords.last}, {/if}{/foreach}{literal}]{/literal}
				{/if}{literal}
			});
		});
		// -->
	</script>
	{/literal}
		<ul id="{$FBV_id|escape}"><li></li></ul>
		{if $FBV_label}<span>{if $FBV_required}{fieldLabel name=$FBV_id key=$FBV_label class="sub_label" required="true"}{else}{fieldLabel name=$FBV_id key=$FBV_label class="sub_label"}{/if}</span>{/if}
	{/if}
</div>
