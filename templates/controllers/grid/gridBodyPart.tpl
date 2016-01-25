{**
 * templates/controllers/grid/gridBodyPart.tpl
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a set of grid rows within a tbody
 *}
<tbody>
	{foreach from=$rows item=row}
		{$row}
	{/foreach}
	<tr></tr>
</tbody>

