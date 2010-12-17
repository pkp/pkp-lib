{**
 * listbuilder.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Displays a ListBuilder object
 *}

{assign var="listbuilderId" value=$listbuilder->getId()}

<div id="{$listbuilderId|escape}" class="listbuilder">
	<div class="wrapper">
		{assign var="additionalData" value=$listbuilder->getAdditionalData()}
		{if !empty($additionalData)}
		<div id="additionalData-{$listbuilderId|escape}{if $itemId}-{$itemId|escape}{/if}">
 			<ul>
				<li>
					{foreach from=$additionalData key=dataKey item=dataValue}
						{if is_array($dataValue)}
							{foreach name="dataArray" from=$dataValue item=arrayValue}
								{assign var="iteration" value=$smarty.foreach.dataArray.iteration}
								<span>
									<input type="hidden" name="additionalData-{$listbuilderId|escape}-{$dataKey|escape}[]" id="additionalData-{$listbuilderId|escape}-{$dataKey|escape}-{$iteration}" value="{$arrayValue|escape}" />
								</span>
							{/foreach}
						{else}
							<span>
								<input type="hidden" name="additionalData-{$listbuilderId|escape}-{$dataKey|escape}" id="additionalData-{$listbuilderId|escape}-{$dataKey|escape}" value="{$dataValue|escape}" />
							</span>
						{/if}
					{/foreach}
				</li>
			</ul>
		</div>
		{/if}
		<div class="unit size2of5" id="source-{$listbuilderId|escape}{if $itemId}-{$itemId|escape}{/if}">
 			<ul>
				<li>
					<label class="desc">
						{translate key=$listbuilder->getTitle()}
					</label>
				  	{if $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_TEXT}
						<span>
							<input type="text" class="field text" id="sourceTitle-{$listbuilderId|escape}" name="sourceTitle-{$listbuilderId|escape}" value="" />
							<label for="sourceTitle-{$listbuilderId|escape}">
								{translate key=$listbuilder->getSourceTitle()}
								<span class="req">*</span>
							</label>
						</span>
						{foreach name="attributes" from=$listbuilder->getAttributeNames() item=attributeName}
							{assign var="iteration" value=$smarty.foreach.attributes.iteration}
							<span>
								<input type="text" class="field text" name="attribute-{$iteration}-{$listbuilderId|escape}" id="attribute-{$iteration}-{$listbuilderId|escape}" value="" />
								<label for="attribute-{$iteration}-{$listbuilderId|escape}">
									{translate key=$attributeName}
									<span class="req">*</span>
								</label>
							</span>
						{/foreach}
					{elseif $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_SELECT}
						<span>
						<select name="selectList-{$listbuilderId|escape}" id="selectList-{$listbuilderId|escape}" class="field select">
							<option>{translate key="manager.setup.selectOne"}</option>
							{foreach from=$listbuilder->getPossibleItemList() item=item}{$item}{/foreach}
						</select>
							<label for="selectList-{$listbuilderId|escape}">
								{translate key=$listbuilder->getSourceTitle()}
								<span class="req">*</span>
							</label>
						</span>
					{elseif $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_BOUND}
						<input type="text" class="textField" id="sourceTitle-{$listbuilderId|escape}{if $itemId}-{$itemId|escape}{/if}" name="sourceTitle-{$listbuilderId|escape}{if $itemId}-{$itemId|escape}{/if}" value="" /> <br />
						<input type="hidden" id="sourceId-{$listbuilderId|escape}{if $itemId}-{$itemId|escape}{/if}" name="sourceId-{$listbuilderId|escape}{if $itemId}-{$itemId|escape}{/if}">
					{/if}
				</li>
			</ul>
		</div>
		<div class="unit size1of10 listbuilder_controls">
			<a href="#" id="add-{$listbuilderId|escape}{if $itemId}-{$itemId|escape}{/if}" onclick="return false;" class="add_item">
				<span class="hidetext">{translate key="common.add"}</span></a>
			<a href="#" id="delete-{$listbuilderId|escape}{if $itemId}-{$itemId|escape}{/if}" onclick="return false;" class="remove_item">
				<span class="hidetext">{translate key="common.delete"}</span></a>
		</div>
		<div id="results-{$listbuilderId|escape}{if $itemId}-{$itemId|escape}{/if}" class="unit size1of2 lastUnit listbuilder_results">
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
	<!--
	{if $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_BOUND}
		{literal}getAutocompleteSource("{/literal}{$autocompleteUrl}{literal}", "{/literal}{$listbuilderId|escape:"javascript"}{if $itemId}-{$itemId|escape:"javascript"}{/if}{literal}");{/literal}
	{/if}
	{literal}
		addItem("{/literal}{$addUrl}{literal}", "{/literal}{$listbuilderId|escape:"javascript"}{if $itemId}-{$itemId|escape:"javascript"}{/if}{literal}", "{/literal}{$localizedButtons|escape:"javascript"}{literal}");
		deleteItems("{/literal}{$deleteUrl}{literal}", "{/literal}{$listbuilderId|escape:"javascript"}{if $itemId}-{$itemId|escape:"javascript"}{/if}{literal}");
		selectRow("{/literal}{$listbuilderId|escape:"javascript"}{if $itemId}-{$itemId|escape:"javascript"}{/if}{literal}");
	{/literal}
	// -->
	</script>
</div>

