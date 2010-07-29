
{**
 * citationExport.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Formatted citation export
 *}
<div class="help-message">
	{if $initialHelpMessage}
		{$initialHelpMessage}
	{else}
		<p>{translate key="submission.citations.editor.export.exportDescription"}</p>
	
		--<p/>
		{foreach from=$formattedCitations key=citationIndex item=formattedCitation}
			<a name="c{$citationIndex+1}_{$formattedCitation|strip_tags|truncate:50:'':false|regex_replace:'/[ ,.;:()]+/':'_'}" ></a>{$formattedCitation}
		{/foreach}
		--
	{/if}
</div>
