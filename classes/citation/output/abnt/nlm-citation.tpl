{**
 * nlm-citation.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * ABNT citation output format template (NLM citation schema based)
 *
 * $Id$
 *}
{strip}
	{assign var=mainTitle value=$nlm30Source|escape|regex_replace:'/:.*$/':''}
	{assign var=subTitle value=$nlm30Source|escape|regex_replace:'/^[^:]+/':''}
	{include file="../abnt/nlm-citation-persons.tpl" persons=$nlm30PersonGroupPersonGroupTypeAuthor}
	{if $nlm30PublicationType == 'book'}
		{if $nlm30ChapterTitle}
			{$nlm30ChapterTitle|escape}
			{if $nlm30PersonGroupPersonGroupTypeEditor}
				. In: {include file="../abnt/nlm-citation-persons.tpl" persons=$nlm30PersonGroupPersonGroupTypeEditor}{literal}(Ed.). {/literal}
			{else}
				{literal}. In: ________. {/literal}
			{/if}
		{/if}
		<i>{$mainTitle}</i>{$subTitle}.
		{if $nlm30PublisherLoc} {$nlm30PublisherLoc|escape}:{/if}
		{if $nlm30PublisherName} {$nlm30PublisherName|escape},{/if}
		{literal} {/literal}{$nlm30Date|truncate:4:''}.
		{if $nlm30Size} {$nlm30Size} p.{/if}
		{if $nlm30Series} ({$nlm30Series|escape}{if $nlm30Volume}, v.{$nlm30Volume|escape}{/if}){/if}
	{elseif $nlm30PublicationType == 'journal'}
		{$nlm30ArticleTitle}. <i>{$mainTitle}</i>{$subTitle}, {if $nlm30PublisherLoc|escape}{$nlm30PublisherLoc|escape},{/if}
		{if $nlm30Volume} v.{$nlm30Volume|escape},{/if}
		{if $nlm30Issue} n.{$nlm30Issue|escape},{/if}
		{if $nlm30Fpage} p.{$nlm30Fpage}{if $nlm30Lpage}-{$nlm30Lpage}{/if},{/if}
		{$nlm30Date|date_format:' %b %Y'|lower}.
	{else}
		{translate key="submission.citations.output.unsupportedPublicationType"}
	{/if}	
{/strip}
