{**
 * listbuilder.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Displays a ListBuilder object
 *}
{assign var="listbuilderId" value=$listbuilder->getId()}


<div id="{$listbuilderId}" class="listbuilder">
	<div class="wrapper">
		<div class="unit size2of5" id="source-{$listbuilderId}{if $itemId}-{$itemId}{/if}">
 			<ul>
		        <li>
		            <label class="desc">
		            	{translate key=$listbuilder->getTitle()}
					</label>
				  	{if $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_TEXT}
						<span>
							<input type="text" class="field text" id="sourceTitle-{$listbuilderId}" name="sourceTitle-{$listbuilderId}" value="" />
							<label for="sourceTitle-{$listbuilderId}">
		                    	{translate key=$listbuilder->getSourceTitle()}
								<span class="req">*</span>
		                	</label>
						</span>
						{foreach name="attributes" from=$listbuilder->getAttributeNames() item=attributeName}
							{assign var="iteration" value=$smarty.foreach.attributes.iteration}
							<span>
								<input type="text" class="field text" name="attribute-{$iteration}-{$listbuilderId}" id="attribute-{$iteration}-{$listbuilderId}" value="" />
								<label for="attribute-{$iteration}-{$listbuilderId}">
									{translate key=$attributeName}
									<span class="req">*</span>
								</label>
							</span>
						{/foreach}
					{elseif $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_SELECT}
						<span>
						<select name="selectList-{$listbuilderId}" id="selectList-{$listbuilderId}" class="field select">
							<option>{translate key='manager.setup.selectOne'}</option>
							{foreach from=$listbuilder->getPossibleItemList() item=item}{$item}{/foreach}
						</select>
							<label for="selectList-{$listbuilderId}">
		                    	{translate key=$listbuilder->getSourceTitle()}
								<span class="req">*</span>
		                	</label>
						</span>
					{elseif $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_BOUND}
						<input type="text" class="textField" id="sourceTitle-{$listbuilderId}{if $itemId}-{$itemId}{/if}" name="sourceTitle-{$listbuilderId}{if $itemId}-{$itemId}{/if}" value="" /> <br />
						<input type="hidden" id="sourceId-{$listbuilderId}{if $itemId}-{$itemId}{/if}" name="sourceId-{$listbuilderId}{if $itemId}-{$itemId}{/if}">
					{/if}
				</li>
			</ul>
		</div>
		<div class="unit size1of10 listbuilder_controls">
			<a href="#" id="add-{$listbuilderId}{if $itemId}-{$itemId}{/if}" onclick="return false;" class="add_item">
				<span class="hidetext">Add</span></a>
			<a href="#" id="delete-{$listbuilderId}{if $itemId}-{$itemId}{/if}" onclick="return false;" class="remove_item">
				<span class="hidetext">Delete</span></a>
		</div>
		<div id="results-{$listbuilderId}{if $itemId}-{$itemId}{/if}" class="unit size1of2 lastUnit listbuilder_results">
		    <ul>
		        <li>
		            <label class="desc">
		                {$listbuilder->getListTitle()|translate}
		            </label>
					{include file="controllers/listbuilder/listbuilderGrid.tpl"}
				</li>
			</ul>
		</div>
	</div>
	<script type='text/javascript'>
	{if $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_BOUND}
		{literal}getAutocompleteSource("{/literal}{$autocompleteUrl}{literal}", "{/literal}{$listbuilderId}{if $itemId}-{$itemId}{/if}{literal}");{/literal}
	{/if}
	{literal}
		addItem("{/literal}{$addUrl}{literal}", "{/literal}{$listbuilderId}{if $itemId}-{$itemId}{/if}{literal}", "{/literal}{$localizedButtons}{literal}");
		deleteItems("{/literal}{$deleteUrl}{literal}", "{/literal}{$listbuilderId}{if $itemId}-{$itemId}{/if}{literal}");
		selectRow("{/literal}{$listbuilderId}{if $itemId}-{$itemId}{/if}{literal}");
	{/literal}
	</script>
</div>

