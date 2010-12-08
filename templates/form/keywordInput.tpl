{**
 * keywordInput.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Keyword input control
 *}

{literal}
<script type="text/javascript">
	<!--
	$(document).ready(function(){
		$("#{/literal}{$FBV_id}{literal}").tagit({
			{/literal}{if $existingInterests}{literal} availableTags: [{/literal}{foreach name=existingInterests from=$FBV_availableKeywords item=interest}"{$interest|escape|escape:"javascript"}"{if !$smarty.foreach.existingInterests.last}, {/if}{/foreach}{literal}],{/literal}{/if}
		      {if $interestsKeywords}{literal}currentTags: [{/literal}{foreach name=currentInterests from=$FBV_currentKeywords item=interest}"{$interest|escape|escape:"javascript"}"{if !$smarty.foreach.currentInterests.last}, {/if}{/foreach}{literal}]{/literal}
		            {else}{literal}currentTags: []{/literal}{/if}{literal}
		});
	});
	// -->
</script>
{/literal}

<div class="keywordInputContainer">
	<ul id="{$FBV_id}"></ul>
	{if $FBV_label}{if $FBV_required}{fieldLabel name=$FBV_id key=$FBV_label required="true"}{else}{fieldLabel name=$FBV_id key=$FBV_label}{/if}{/if}
</div>

