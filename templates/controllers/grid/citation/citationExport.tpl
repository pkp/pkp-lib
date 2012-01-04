
{**
 * citationExport.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Formatted citation export
 *
 * $Id$
 *}
<div id="citationExport">
	<h3>{translate key="submission.citations.grid.exportCitations"}</h3>

	<p>{translate key="submission.citations.output.exportDescription"}</p>

	--<br/>
	{foreach from=$formattedCitations key=citationIndex item=formattedCitation}
		<a name="c{$citationIndex+1}_{$formattedCitation|strip_tags|truncate:50:'':false|regex_replace:'/[ ,.;:()]+/':'_'}" ></a>{$formattedCitation}<br/>
	{/foreach}
	--
</div>
