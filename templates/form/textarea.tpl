{**
 * templates/form/textArea.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form text area
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
			<textarea id="{$FBV_id|escape}-{$formLocale|escape}" {$FBV_textAreaParams}
				class="localizable {$FBV_class} {$FBV_height}{if $FBV_validation} {$FBV_validation|escape}{/if}{if $formLocale != $currentLocale} locale_{$formLocale|escape}{/if}{if $FBV_rich} richContent{/if}"
				{if $FBV_disabled} disabled="disabled"{/if}
				name="{$FBV_name|escape}[{$formLocale|escape}]">{$FBV_value[$formLocale]|escape}
			</textarea>
		{/strip}

		{$FBV_label_content}

		<span>
			<div class="localization_popover">
				{foreach from=$formLocales key=thisFormLocale item=thisFormLocaleName}{if $formLocale != $thisFormLocale}
					{strip}
					<textarea id="{$FBV_id|escape}-{$thisFormLocale|escape}" {$FBV_textAreaParams}
						placeholder="{$thisFormLocaleName|escape}" 
						class="locale_{$thisFormLocale|escape} {$FBV_class} {$FBV_height}{if $FBV_rich} richContent{/if}" 
						{if $FBV_disabled} disabled="disabled"{/if}
						name="{$FBV_name|escape}[{$thisFormLocale|escape}]">{$FBV_value[$thisFormLocale]|escape}
					</textarea>
					{/strip}
					<label for="{$FBV_id|escape}-{$thisFormLocale|escape}" class="locale">({$thisFormLocaleName|escape})</label>
				{/if}{/foreach}
			</div>
		</span>
	</span>
{else}
	{* This is not a multilingual control. *}
	<textarea {$FBV_textAreaParams}
		class="{$FBV_class} {$FBV_height}{if $FBV_validation} {$FBV_validation|escape}{/if}{if $FBV_rich} richContent{/if}"
		{if $FBV_disabled} disabled="disabled"{/if}
		name="{$FBV_name|escape}"
		id="{$FBV_id|escape}">{$FBV_value|escape}</textarea>

		<span>{$FBV_label_content}</span>
{/if}
</div>