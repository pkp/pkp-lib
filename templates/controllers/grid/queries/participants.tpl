{**
 * templates/controllers/grid/queries/participants.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Show the list of participants.
 *
 *}
{foreach from=$participants item=user}
	<li>{$user->getFullName()|escape} ({$user->getUsername()|escape})</li>
{/foreach}
