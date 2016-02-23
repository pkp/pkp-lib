{**
 * templates/common/helpLink.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief A link which can request the help panel open to a specific chapter
 *  and section
 *
 * @uses $chapter string Chapter name, eg - chapter_6_submissions.md
 * @uses $section string Section reference, eg - second
 * @uses $text string Text for the link
 * @uses $textKey string Locale key for the link text
 *}
<a href="#" class="requestHelpPanel pkp_help_link" data-topic="{$chapter|escape}{if $section}#{$section|escape}{/if}">
	{if $textKey}
		{translate key=$textKey}
	{elseif $text}
		{$text}
	{else}
		{translate key="help.help"}
	{/if}
</a>
