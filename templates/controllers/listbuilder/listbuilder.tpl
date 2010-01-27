{**
 * listbuilder.tpl
 *
 * Copyright (c) 2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Displays a ListBuilder object
 *}
{assign var="listbuilderId" value=$listbuilder->getId()}


<div id="{$listbuilderId}" class="listbuilder">
	<div class="wrapper">
		<div class="unit size2of5" id="source-{$listbuilderId}">
 			<ul>
		        <li>
		            <label class="desc">
		            	{translate key=$listbuilder->getSourceTitle()}
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
						<input type="text" class="textField" size="30" id="sourceTitle-{$listbuilderId}" name="sourceTitle-{$listbuilderId}" value="" /> <br />
						<input type="hidden" id="sourceId-{$listbuilderId}" name="sourceId-{$listbuilderId}">
					{/if}
				</li>
			</ul>
		</div>
		<div class="unit size1of10 listbuilder_controls">
			<a href="#" id="add-{$listbuilderId}" onclick="return false;" class="add_item">
				<span class="hidetext">Add</span></a>
			<br />
			<a href="#" id="delete-{$listbuilderId}" onclick="return false;" class="remove_item">
				<span class="hidetext">Delete</span></a>
		</div>
		<div id="results-{$listbuilderId}" class="unit size1of2 lastUnit listbuilder_results">
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
		{literal}getAutocompleteSource("{/literal}{$autocompleteUrl}{literal}", "{/literal}{$listbuilderId}{literal}");{/literal}
	{/if}
	{literal}
		addItem("{/literal}{$addUrl}{literal}", "{/literal}{$listbuilderId}{literal}", "{/literal}{$localizedButtons}{literal}");
		deleteItems("{/literal}{$deleteUrl}{literal}", "{/literal}{$listbuilderId}{literal}");
		selectRow("{/literal}{$listbuilderId}{literal}");
	{/literal}
	</script>
</div>

