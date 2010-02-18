{**
 * nlm-citation-persons.tpl
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * ABNT citation output format template (NLM citation schema based) - person list
 *
 * $Id$
 *}
{strip}
	{foreach from=$persons item=person name=persons key=personIndex}
		{if $person->getStatement('prefix')}{$person->getStatement('prefix')|escape|upper} {/if}{$person->getStatement('surname')|escape|upper}, {if $person->getStatement('suffix')}{$person->getStatement('suffix')|escape|upper} {/if}
		{foreach from=$person->getStatement('given-names') item=givenName}{$givenName[0]|escape}.{/foreach}
		{if $smarty.foreach.persons.last} {else}; {/if}
	{/foreach}
{/strip}