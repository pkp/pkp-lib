{**
 * templates/controllers/grid/gridCell.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a url grid cell
 *}
{assign var=cellId value="cell-"|concat:$id}
<span id="{$cellId}">
	<a href="{$label|escape}">{$label|escape}</a>
</span>

