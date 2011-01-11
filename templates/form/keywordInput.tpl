{**
 * keywordInput.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Keyword input control
 *}

{literal}
<script type="text/javascript">
	<!--
	$(document).ready(function(){
		$("#{/literal}{$FBV_id}{literal}").tagit({
			{/literal}{if $FBV_availableKeywords}{literal}
				// This is the list of keywords in the system used to populate the autocomplete
				availableTags: [{/literal}{foreach name=existingInterests from=$FBV_availableKeywords item=interest}"{$interest|escape|escape:'javascript'}"{if !$smarty.foreach.existingInterests.last}, {/if}{/foreach}{literal}],
			{/literal}{/if}
			{if $FBV_currentKeywords}{literal}
				// This is the list of the user's keywords that have already been saved
				currentTags: [{/literal}{foreach name=currentInterests from=$FBV_currentKeywords item=interest}"{$interest|escape|escape:'javascript'}"{if !$smarty.foreach.currentInterests.last}, {/if}{/foreach}{literal}]{/literal}
			{/if}{literal}
		});
	});
	// -->
</script>
{/literal}

<div class="keywordInputContainer">
	<ul id="{$FBV_id}"><li></li></ul>
	{if $FBV_label}{if $FBV_required}{fieldLabel name=$FBV_id key=$FBV_label required="true"}{else}{fieldLabel name=$FBV_id key=$FBV_label}{/if}{/if}
</div>
