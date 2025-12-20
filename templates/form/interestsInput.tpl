{**
 * templates/user/interestsInput.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Keyword input control for user interests
 *}
<script>
	$(document).ready(function(){ldelim}
		$("#{$FBV_id|escape}").find(".interests").tagit({ldelim}
			fieldName: 'interests[]',
			allowSpaces: true,
			autocomplete: {ldelim}
				source: function(request, response) {ldelim}
					$.ajax({ldelim}
						url: {url|json_encode router=PKP\core\PKPApplication::ROUTE_API endpoint='vocabs/interests' escape=false},
						data: {ldelim}'term': request.term{rdelim},
						dataType: 'json',
						success: function(jsonData) {ldelim}
							// Extract interest names from the API response
							var interests = jsonData.map(function(item) {ldelim}
								return item.name;
							{rdelim});
							response(interests);
						{rdelim}
					{rdelim});
				{rdelim}
			{rdelim}
		{rdelim});
	{rdelim});
</script>

<div id="{$FBV_id|escape}">
	<!-- The container which will be processed by tag-it.js as the interests widget -->
	<ul class="interests">
		{if $FBV_interests}{foreach from=$FBV_interests item=interest}<li class="hidden">{$interest|escape}</li>{/foreach}{/if}
	</ul>
	{if $FBV_label_content}<span>{$FBV_label_content}</span>{/if}
</div>
