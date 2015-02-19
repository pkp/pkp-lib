{**
 * templates/form/textInput.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form text input
 *}

{assign var="uniqId" value="-"|uniqid|escape}
<div{if $FBV_layoutInfo} class="{$FBV_layoutInfo}"{/if}>
{if $FBV_multilingual && count($formLocales) > 1}
	<script type="text/javascript">
	$(function() {ldelim}
		$('#{$FBV_id|escape:javascript}-localization-popover-container{$uniqId}').pkpHandler(
			'$.pkp.controllers.form.MultilingualInputHandler'
			);
	{rdelim});
	</script>
	{* This is a multilingual control. Enable popover display. *}
	<span id="{$FBV_id|escape}-localization-popover-container{$uniqId}" class="localization_popover_container">
		{strip}
		<input type="{if $FBV_isPassword}password{else}text{/if}"
			{$FBV_textInputParams}
			class="localizable {if $FBV_class}{$FBV_class|escape}{/if}{if $FBV_validation} {$FBV_validation}{/if}{if $formLocale != $currentLocale} locale_{$formLocale|escape}{/if}"
			{if $FBV_disabled} disabled="disabled"{/if}
			value="{$FBV_value[$formLocale]|escape}"
			name="{$FBV_name|escape}[{$formLocale|escape}]"
			id="{$FBV_id|escape}-{$formLocale|escape}{$uniqId}"
		/>
		{/strip}

		{$FBV_label_content}

		<span>
			<div class="localization_popover">
				{foreach from=$formLocales key=thisFormLocale item=thisFormLocaleName}{if $formLocale != $thisFormLocale}
					{strip}
					<input	type="{if $FBV_isPassword}password{else}text{/if}"
						{$FBV_textInputParams}
						placeholder="{$thisFormLocaleName|escape}"
						class="multilingual_extra flag flag_{$thisFormLocale|escape}{if $FBV_sizeInfo} {$FBV_sizeInfo|escape}{/if}"
						{if $FBV_disabled} disabled="disabled"{/if}
						value="{$FBV_value[$thisFormLocale]|escape}"
						name="{$FBV_name|escape}[{$thisFormLocale|escape}]"
						id="{$FBV_id|escape}-{$thisFormLocale|escape}{$uniqId}"
						{if $FBV_tabIndex} tabindex="{$FBV_tabIndex|escape}"{/if}
					/>
					{/strip}
					<label for="{$FBV_id|escape}-{$thisFormLocale|escape}{$uniqId}" class="locale">({$thisFormLocaleName|escape})</label>
				{/if}{/foreach}
			</div>
		</span>
	</span>
{else}
	{* This is not a multilingual control or there is only one locale available *}
	<input	type="{if $FBV_isPassword}password{else}text{/if}"
		{$FBV_textInputParams}
		class="field text{if $FBV_class} {$FBV_class|escape}{/if}{if $FBV_validation} {$FBV_validation}{/if}"
		{if $FBV_disabled} disabled="disabled"{/if}
		name="{$FBV_name|escape}{if $FBV_multilingual}[{$formLocale|escape}]{/if}"
		value="{if $FBV_multilingual}{$FBV_value[$formLocale]|escape}{else}{$FBV_value|escape}{/if}"
		id="{$FBV_id|escape}{$uniqId}"
		{if $FBV_tabIndex} tabindex="{$FBV_tabIndex|escape}"{/if}
	/>

	<span>{$FBV_label_content}</span>
{/if}
</div>
