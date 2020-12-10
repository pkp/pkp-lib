{**
 * templates/controllers/grid/queries/participants.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Show the list of participants.
 *
 *}
{foreach from=$participants item=user}
	<li>{$user->getFullName()|escape} ({$user->getUsername()|escape})</li>
{/foreach}
