{**
 * templates/form/checkboxGroup.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form checkboxgroup
 *}
			{assign var=validationId value=$FBV_name|escape:"javascript"}
			{assign var="funcName" value=$FBV_name|escape:"javascript"|concat:"checkvalid()"}
			<script type="text/javascript">
				function {$funcName} {ldelim}
					if ($('#{$FBV_name} input:checked').length > 0) {ldelim}
						$('#{$validationId}-valid').val(true);
					{rdelim}
					else {ldelim}
						$('#{$validationId}-valid').val(null);
					{rdelim}
			{rdelim}
			</script>

	{if $FBV_required}
		{if !empty($FBV_selected)}{assign var="valid" value="true"}{else}{assign var="valid" value=""}{/if}
		<input type="hidden" class="required" validation="required" value="{$valid}" id={$validationId}-valid />
	{/if}
	
	<span id="{$FBV_name}">
	{if $FBV_translate}
		{html_checkboxes_translate class="field checkbox" onclick="$funcName" name=$FBV_name options=$FBV_from selected=$FBV_selected}
	{else}
		{html_checkboxes class="field checkbox" onclick="$funcName" name=$FBV_name options=$FBV_from selected=$FBV_selected}
	{/if}
	</span>
<span>{$FBV_label_content}</span>
