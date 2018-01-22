{**
 * templates/form/fieldLabel.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form field label
 *}

<label{if !$FBV_suppressId} for="{$FBV_name|escape}"{/if}{if $FBV_class} class="{$FBV_class|escape}"{/if} >
	{$FBV_label}{if $FBV_required}<span class="req">*</span>{/if}
</label>

