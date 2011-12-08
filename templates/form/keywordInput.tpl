{**
 * templates/form/keywordInput.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Generic keyword input control
 *}

<script type="text/javascript">
	$(document).ready(function(){ldelim}
		$("#{$FBV_id}").tagit({ldelim}
			itemName: "keywords",
			fieldName: "{$FBV_id|escape}",
			allowSpaces: true,
			availableTags: [{foreach name=availableKeywords from=$FBV_availableKeywords item=availableKeyword}"{$availableKeyword|escape|escape:'javascript'}"{if !$smarty.foreach.availableKeywords.last}, {/if}{/foreach}]
		{rdelim});

		{** Tag-it has no "read-only" option, so we must remove input elements to disable the widget **}
		{if $FBV_disabled}
			$("#{$FBV_id|escape}").find('.tagit-close, .tagit-new').remove();
		{/if}
	{rdelim});
</script>

<!-- The container which will be processed by tag-it.js as the interests widget -->
<ul id="{$FBV_id|escape}">
	{if $FBV_currentKeywords|escape}{foreach from=$FBV_currentKeywords item=currentKeyword}<li>{$currentKeyword|escape}</li>{/foreach}{/if}
</ul>
{if $FBV_label_content|escape}<span>{$FBV_label_content|escape}</span>{/if}
<br />