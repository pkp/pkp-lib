{**
 * nlm-citation-persons.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * APA citation output format template (NLM citation schema based) - person list
 *
 * $Id$
 *}
{strip}
	{foreach from=$persons item=person name=persons key=personIndex}
		{if $personIndex < 6 || $smarty.foreach.persons.last}
			{capture assign=surname}
				{if $person->getStatement('prefix')}{$person->getStatement('prefix')|escape} {/if}{$person->getStatement('surname')|escape}
			{/capture}	
			{capture assign=initials}
				{foreach from=$person->getStatement('given-names') item=givenName name=givenNames}
					{$givenName[0]|escape}.{if !$smarty.foreach.givenNames.last} {/if}
				{/foreach}
			{/capture}
			{if $smarty.foreach.persons.last && $personIndex>0}{if $personIndex >6}. . . {else}& {/if}{/if}
			{if $reversed}{$surname}, {$initials}{else}{$initials} {$surname}{/if}
			{if $person->getStatement('suffix')}, {$person->getStatement('suffix')|escape}{/if}
			{if $smarty.foreach.persons.last || (count($persons) == 2 && !$reversed)} {else}, {/if}
		{/if}
	{/foreach}
{/strip}
