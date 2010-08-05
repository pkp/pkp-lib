{**
 * nlm.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * NLM Journal Publication Tag Set 3.0 XML document output.
 *
 * FIXME: This currently is partial NLM XML output (reference lists). Create
 * full NLM mark-up when we have full document support.
 *}
<ref-list>
	{foreach from=$citationsMarkup key=seq item=citationMarkup}
		<ref id="B{$seq}">
			<label>{$seq}</label>
			{$citationMarkup}
		</ref>
	{/foreach}
</ref-list>