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
 * @uses $helpFile string Markdown filename, eg - chapter_6_submissions.md
 * @uses $helpSection string Section reference, eg - second
 * @uses $helpText string Text for the link
 * @uses $helpTextKey string Locale key for the link text
 *}
<a href="#" class="requestHelpPanel pkp_help_link {$helpClass|escape}" data-topic="{$helpFile|escape}{if $helpSection}#{$helpSection|escape}{/if}">
	{if $helpText}
		{$text|escape}
	{else}
		{translate key=$helpTextKey}
	{/if}
</a>
