{**
 * gridCell.tpl
 *
 * Copyright (c) 2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a url grid cell
 *}
{assign var=cellId value="cell-`$id`"}
<td id="{$cellId}">
	<a href="{$label}">{$label}</a>
</td>