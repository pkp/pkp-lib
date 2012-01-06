{**
 * templates/form/keywordInput.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Generic keyword input control
 *}

{if $FBV_multilingual}
	{foreach from=$formLocales key=thisFormLocale item=thisFormLocaleName}
		<script type="text/javascript">
			$(document).ready(function(){ldelim}
				$("#{$thisFormLocale|escape}-{$FBV_id}").tagit({ldelim}
					itemName: "keywords",
					fieldName: "{$thisFormLocale|escape}-{$FBV_id|escape}",
					allowSpaces: true,
					{if $FBV_sourceUrl} {* this url should return a JSON array of possible keywords *}
						tagSource: function(search, showChoices) {ldelim}
							$.ajax({ldelim}
								url: "{$FBV_sourceUrl}",
								data: search,
								success: function(choices) {ldelim}
									showChoices(choices);
								{rdelim}
							{rdelim});
						{rdelim}
					{else}
						availableTags: [{foreach name=availableKeywords from=$FBV_availableKeywords.$thisFormLocale item=availableKeyword}"{$availableKeyword|escape|escape:'javascript'}"{if !$smarty.foreach.availableKeywords.last}, {/if}{/foreach}]
					{/if}
				{rdelim});
		
				{** Tag-it has no "read-only" option, so we must remove input elements to disable the widget **}
				{if $FBV_disabled}
					$("#{$thisFormLocale|escape}-{$FBV_id|escape}").find('.tagit-close, .tagit-new').remove();
				{/if}
			{rdelim});
		</script>
	{/foreach}

	<script type="text/javascript">
		$(function() {ldelim}
			$('#{$FBV_id|escape:javascript}-localization-popover-container').pkpHandler(
				'$.pkp.controllers.form.MultilingualInputHandler'
				);
		{rdelim});
		</script>
		<span id="{$FBV_id|escape}-localization-popover-container" class="localization_popover_container pkpTagit">
			<ul class="localization_popover_container localizable {if $formLocale != $currentLocale} locale_{$formLocale|escape}{/if}" id="{$formLocale|escape}-{$FBV_id|escape}">
				{if $FBV_currentKeywords}{foreach from=$FBV_currentKeywords.$formLocale item=currentKeyword}<li>{$currentKeyword|escape}</li>{/foreach}{/if}
			</ul>
			{if $FBV_label_content}<span>{$FBV_label_content}</span>{/if}
			<br />
			<span>
				<div class="localization_popover">
					{foreach from=$formLocales key=thisFormLocale item=thisFormLocaleName}{if $formLocale != $thisFormLocale}
						<ul class="multilingual_extra flag flag_{$thisFormLocale|escape}" id="{$thisFormLocale|escape}-{$FBV_id|escape}">
							{if $FBV_currentKeywords}{foreach from=$FBV_currentKeywords.$thisFormLocale item=currentKeyword}<li>{$currentKeyword|escape}</li>{/foreach}{/if}
						</ul>
					{/if}{/foreach}
				</div>
			</span>
		</span>
		
{else} {* this is not a multilingual keyword field *}
	<script type="text/javascript">
		$(document).ready(function(){ldelim}
			$("#{$FBV_id}").tagit({ldelim}
				itemName: "keywords",
				fieldName: "{$FBV_id|escape}",
				allowSpaces: true,
				{if $FBV_sourceUrl}
					tagSource: function(search, showChoices) {ldelim}
						$.ajax({ldelim}
							url: "{$FBV_sourceUrl}", {* this url should return a JSON array of possible keywords *}
							data: search,
							success: function(choices) {ldelim}
								showChoices(choices);
							{rdelim}
						{rdelim});
					{rdelim}
				{else}
					availableTags: [{foreach name=availableKeywords from=$FBV_availableKeywords item=availableKeyword}"{$availableKeyword|escape|escape:'javascript'}"{if !$smarty.foreach.availableKeywords.last}, {/if}{/foreach}]
				{/if}
			{rdelim});
	
			{** Tag-it has no "read-only" option, so we must remove input elements to disable the widget **}
			{if $FBV_disabled}
				$("#{$FBV_id|escape}").find('.tagit-close, .tagit-new').remove();
			{/if}
		{rdelim});
	</script>
	
	<!-- The container which will be processed by tag-it.js as the interests widget -->
	<ul id="{$FBV_id|escape}">
		{if $FBV_currentKeywords}{foreach from=$FBV_currentKeywords item=currentKeyword}<li>{$currentKeyword|escape}</li>{/foreach}{/if}
	</ul>
	{if $FBV_label_content}<span>{$FBV_label_content}</span>{/if}
	<br />
{/if}
