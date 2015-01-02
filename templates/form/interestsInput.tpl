{**
 * templates/user/interestsInput.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Keyword input control for user interests
 *
 * Note: This template is called explicitly in OJS and OCS and called by the
 *  FBV in OMP.  Please be careful if changing variable names.
 *}

{if !$FBV_id}{assign var='FBV_id' value='interests'}{/if}

<script type="text/javascript">
	$(document).ready(function(){ldelim}
		$("#{$FBV_id|escape}").find(".interestsTextOnly").html(null).hide();
		$("#{$FBV_id|escape}").find(".interestDescription").show();
		$("#{$FBV_id|escape}").find(".interests").tagit({ldelim}
			itemName: "keywords",
			fieldName: "interests",
			allowSpaces: true,
			tagSource: function(search, showChoices) {ldelim}
				$.ajax({ldelim}
					url: "{url|escape:'javascript' router=$smarty.const.ROUTE_PAGE page='user' op='getInterests' escape=false}",
					data: search,
					dataType: 'json',
					success: function(jsonData) {ldelim}
						if (jsonData.status == true) {ldelim}
							// Must explicitly escape
							// WARNING: jquery-UI > 1.8.3 supposedly auto-escapes these values.  Reinvestigate when we upgrade.
							var results = $.map(jsonData.content, function(item) {ldelim}
								return escapeHTML(item);
							{rdelim});
							showChoices(results);
						{rdelim}
					{rdelim}
				{rdelim});
			{rdelim}
		{rdelim});
	{rdelim});
</script>

<div id="{$FBV_id|escape}">
	<!-- The container which will be processed by tag-it.js as the interests widget -->
	<ul class="interests">
		{if $FBV_interestsKeywords}{foreach from=$FBV_interestsKeywords item=interest}<li class="hidden">{$interest|escape}</li>{/foreach}{/if}
	</ul>
	{if $FBV_label_content}<span>{$FBV_label_content}</span>{/if}
	<!-- If Javascript is disabled, this field will be visible -->
	<textarea name="interestsTextOnly" rows="5" cols="40" class="interestsTextOnly textArea">{if $FBV_interestsTextOnly}{$FBV_interestsTextOnly|escape}{/if}</textarea>
</div>
