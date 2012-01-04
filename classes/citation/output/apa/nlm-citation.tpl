{**
 * nlm-citation.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * APA citation output format template (NLM citation schema based)
 *
 * $Id$
 *}
{strip}
	{if $nlm30PublicationType != 'book' && $nlm30PublicationType != 'journal'}
		{translate key="submission.citations.output.unsupportedPublicationType"}
	{else}
		{if $nlm30PersonGroupPersonGroupTypeAuthor}
			{include file="../apa/nlm-citation-persons.tpl" persons=$nlm30PersonGroupPersonGroupTypeAuthor reversed=true}
		{else}{$nlm30Source|escape} {/if}
		({$nlm30Date|truncate:4:''})
		{if $nlm30PublicationType == 'book'}
			{if $nlm30ChapterTitle}
				{literal} {/literal}{$nlm30ChapterTitle|escape}
				{if $nlm30PersonGroupPersonGroupTypeEditor}
					. In: {include file="../apa/nlm-citation-persons.tpl" persons=$nlm30PersonGroupPersonGroupTypeEditor}{if count($nlm30PersonGroupPersonGroupTypeEditor)>1}(Eds.), {else}(Ed.), {/if}
				{else}
					{literal}. In: {/literal}
				{/if}
			{else}
				{literal}. {/literal}
			{/if}
			{if $nlm30PersonGroupPersonGroupTypeAuthor}<i>{$nlm30Source|escape}</i>{/if}
			{if $nlm30ChapterTitle && $nlm30Fpage} (p{if $nlm30Lpage}p{/if}.{$nlm30Fpage}{if $nlm30Lpage}-{$nlm30Lpage}{/if}){/if}
			{if $nlm30PersonGroupPersonGroupTypeAuthor || ($nlm30ChapterTitle && $nlm30Fpage)}. {/if}
			{if $nlm30PublisherLoc}{$nlm30PublisherLoc|escape}{/if}
			{if $nlm30PublisherName}: {$nlm30PublisherName|escape}{/if}.
		{elseif $nlm30PublicationType == 'journal'}
			. {$nlm30ArticleTitle|escape}. <i>{$nlm30Source|escape}, </i>
			{if $nlm30Volume}{$nlm30Volume|escape}{/if}
			{if $nlm30Issue}{if $nlm30Volume}({$nlm30Issue|escape}){else}{$nlm30Issue|escape}{/if}{/if}
			{if $nlm30Volume || $nlm30Issue}, {/if}
			{if $nlm30Fpage}{$nlm30Fpage}{if $nlm30Lpage}-{$nlm30Lpage}{/if}.{/if}
		{/if}
		{if $nlm30PubIdPubIdTypeDoi} doi:{$nlm30PubIdPubIdTypeDoi|escape}{/if}
	{/if}	
{/strip}
