{**
 * templates/controllers/grid/queries/readQuery.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Read a query.
 *
 *}
<div class="readQuery">
	<h3>{if $headNote}{$headNote->getTitle()|escape}{else}&mdash;{/if}</h3>
	{iterate from=notes item=note}
		<div id="note-{$note->getId()|escape}">{$note->getContents()|strip_unsafe_html}</div>
	{/iterate}
	{if $notes->wasEmpty()}
		<div id="note-none">&nbsp;</div>
	{/if}
</div>
