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
	$(document).ready(function(){
		$("#{/literal}{$FBV_id}{literal}").tagit({
			availableTags: [{/literal}{$FBV_availableKeywords}{literal}]
			{/literal}{if $FBV_currentKeywords}{literal}, currentTags: [{/literal}{$FBV_currentKeywords}]{/if}{literal}
		});
	});
</script>
{/literal}

<div class="keywordInputContainer">
	<ul id="{$FBV_id}"></ul>
	{if $FBV_label}{if $FBV_required}{fieldLabel name=$FBV_id key=$FBV_label required="true"}{else}{fieldLabel name=$FBV_id key=$FBV_label}{/if}{/if}
</div>

