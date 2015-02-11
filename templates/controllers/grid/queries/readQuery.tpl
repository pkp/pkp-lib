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

<div id="readQuery">

	<p>{$query->getLocalizedSubject()|escape}</p>

	<p>
	{$query->getLocalizedComment()|escape}
	</p>
</div>
