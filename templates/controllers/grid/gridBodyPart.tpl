{**
 * gridBodyPart.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a set of grid rows within a tbody
 *}
<tbody>
	{foreach from=$rows item=row}
		{$row}
	{/foreach}	
</tbody>
