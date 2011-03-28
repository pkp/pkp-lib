{**
 * templates/form/textInput.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form text input
 *}

{if $FBV_multilingual}
	{* This is a multilingual control. Enable popover display. *}
	<span class="pkp_controllers_form_localization_container">
		{strip}
		<input	type="{if $FBV_isPassword}password{else}text{/if}"
			{$FBV_textInputParams}
			class="field multilingual_primary text{if $FBV_sizeInfo} {$FBV_sizeInfo|escape}{/if}{if $FBV_validation} {$FBV_validation}{/if}{if $formLocale != $currentLocale} locale_{$formLocale|escape}{/if}"
			{if $FBV_disabled} disabled="disabled"{/if}
			value="{$FBV_value[$formLocale]|escape}"
			name="{$FBV_name|escape}[{$formLocale|escape}]"
			id="{$FBV_id|escape}-{$formLocale|escape}"
		/>
		{/strip}

		{$FBV_label_content}

		<span>
			<div class="pkp_controllers_form_localization_popover">
				{foreach from=$formLocales key=thisFormLocale item=thisFormLocaleName}{if $formLocale != $thisFormLocale}
					{strip}
					<input	type="{if $FBV_isPassword}password{else}text{/if}"
						{$FBV_textInputParams}
						placeholder="{$thisFormLocaleName|escape}"
						class="field text multilingual_extra locale_{$thisFormLocale|escape}{if $FBV_sizeInfo} {$FBV_sizeInfo|escape}{/if}"
						{if $FBV_disabled} disabled="disabled"{/if}
						value="{$FBV_value[$thisFormLocale]|escape}"
						name="{$FBV_name|escape}[{$thisFormLocale|escape}]"
						id="{$FBV_id|escape}-{$thisFormLocale|escape}"
					/>
					{/strip}
					<label class="multilingual_extra_label" for="{$FBV_id|escape}-{$thisFormLocale|escape}" class="locale">({$thisFormLocaleName|escape})</label>
				{/if}{/foreach}
			</div>
		</span>
	</span>
{else}
	{* This is not a multilingual control. *}
	<input type="{if $FBV_isPassword}password{else}text{/if}" {$FBV_textInputParams} class="field text{if $FBV_sizeInfo} {$FBV_sizeInfo|escape}{/if}{if $FBV_validation} {$FBV_validation}{/if}"{if $FBV_disabled} disabled="disabled"{/if}/>
{/if}
