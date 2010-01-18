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
	<span class="title"><h5>{translate key=$listbuilder->getTitle()}</h5></span>
	
	<div class="wrapper">
		<div class="source" id="source-{$listbuilderId}">
		<label for="sourceTitle">{translate key=$listbuilder->getSourceTitle()}</label> <br />
		{if $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_TEXT}
			<input type="text" class="textField" size="30" id="sourceTitle-{$listbuilderId}" name="sourceTitle-{$listbuilderId}" value="" /> <br />
			{foreach name="attributes" from=$listbuilder->getAttributeNames() item=attributeName}
				{assign var="iteration" value=$smarty.foreach.attributes.iteration}
				<label for="attribute-{$iteration}-{$listbuilderId}">{translate key=$attributeName}</label> <br />
				<input type="text" class="textField" size="30" name="attribute-{$iteration}-{$listbuilderId}" id="attribute-{$iteration}-{$listbuilderId}" value="" /> <br />
			{/foreach}
		{elseif $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_SELECT}
			<select name="selectList-{$listbuilderId}" id="selectList-{$listbuilderId}" class="selectList">
				{foreach from=$listbuilder->getPossibleItemList() item=item}{$item}{/foreach}
			</select>
			<br />
		{elseif $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_BOUND}
			<input type="text" class="textField" size="30" id="sourceTitle-{$listbuilderId}" name="sourceTitle-{$listbuilderId}" value="" /> <br />
			<input type="hidden" id="sourceId-{$listbuilderId}" name="sourceId-{$listbuilderId}">
		{/if}
		</div>

		<div class="actions">
			<input id="add-{$listbuilderId}" onclick="return false;" type="image" src="{$baseUrl}/lib/pkp/templates/images/icons/action_forward.gif" alt={translate key="grid.action.addItem"}> <br />
			<input id="delete-{$listbuilderId}" onclick="return false;" type="image" src="{$baseUrl}/lib/pkp/templates/images/icons/delete.gif" alt={translate key="grid.action.delete"}> <br />
		</div>
		
	
		<div class="list">
			{include file="controllers/listbuilder/listbuilderGrid.tpl"}
		</div>
	</div>
	<br style="clear:left"/>
	<br />
	
	
	
	<script type='text/javascript'>
	{if $listbuilder->getSourceType() == $smarty.const.LISTBUILDER_SOURCE_TYPE_BOUND}
		{literal}getAutocompleteSource("{/literal}{$autocompleteUrl}{literal}", "{/literal}{$listbuilderId}{literal}");{/literal}
	{/if}
	{literal}
		addItem("{/literal}{$addUrl}{literal}", "{/literal}{$listbuilderId}{literal}");
		deleteItems("{/literal}{$deleteUrl}{literal}", "{/literal}{$listbuilderId}{literal}");
		selectRow("{/literal}{$listbuilderId}{literal}");
	{/literal}	
	</script>
</div>

