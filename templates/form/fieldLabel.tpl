{**
 * templates/form/fieldLabel.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form field label
 *}

<label{if !$suppressId} for="{$name}"{/if}{if $class} class="{$class}"{/if} >
	{$label} {if $required}*{/if}
</label>

