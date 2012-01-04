{**
 * templates/form/textInput.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form text input
 *}

<div{if $FBV_layoutInfo} class="{$FBV_layoutInfo}"{/if}>
{if $FBV_multilingual}
	<script type="text/javascript">
	$(function() {ldelim}
		$('#{$FBV_name|escape:javascript}-localization-popover-container').pkpHandler(
			'$.pkp.controllers.form.MultilingualInputHandler'
			);
	{rdelim});
	</script>
	{* This is a multilingual control. Enable popover display. *}
	<span id="{$FBV_name|escape}-localization-popover-container" class="localization_popover_container">
		{strip}
		<input type="{if $FBV_isPassword}password{else}text{/if}"
			{$FBV_textInputParams}
			class="localizable {if $FBV_class}{$FBV_class|escape}{/if}{if $FBV_validation} {$FBV_validation}{/if}{if $formLocale != $currentLocale} locale_{$formLocale|escape}{/if}"
			{if $FBV_disabled} disabled="disabled"{/if}
			value="{$FBV_value[$formLocale]|escape}"
			name="{$FBV_name|escape}[{$formLocale|escape}]"
			id="{$FBV_id|escape}-{$formLocale|escape}"
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
						id="{$FBV_id|escape}-{$thisFormLocale|escape}"
					/>
					{/strip}
					<label for="{$FBV_id|escape}-{$thisFormLocale|escape}" class="locale">({$thisFormLocaleName|escape})</label>
				{/if}{/foreach}
			</div>
		</span>
	</span>
{else}
	{* This is not a multilingual control. *}
	<input	type="{if $FBV_isPassword}password{else}text{/if}"
		{$FBV_textInputParams}
		class="field text{if $FBV_class} {$FBV_class|escape}{/if}{if $FBV_validation} {$FBV_validation}{/if}"
		{if $FBV_disabled} disabled="disabled"{/if}
		name="{$FBV_name|escape}"
		value="{$FBV_value|escape}"
		id="{$FBV_id|escape}"
	/>

	<span>{$FBV_label_content}</span>
{/if}
</div>
