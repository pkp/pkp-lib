<tbody>
	{if $categoryName}
		<tr class="category group{$categoryNum}">
			<td colspan="{$numColumns}">{$categoryName}</td>
		</tr>
	{/if}
	{foreach from=$rows item=row}
		{$row}
	{/foreach}
</tbody>