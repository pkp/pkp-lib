{**
 * gridCellInSpan.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a grid cell that uses a <span> tag instead of a <td> ( to be used as a type of sub-cell)
 *}
{assign var=cellId value="cell-"|concat:$id}
<span id="{$cellId}">
	{$label}
</span>
