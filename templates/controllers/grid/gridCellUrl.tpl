{**
 * gridCell.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a url grid cell
 *}
{assign var=cellId value="cell-"|concat:$id}
<td id="{$cellId}">
	<a href="{$label}">{$label}</a>
</td>
