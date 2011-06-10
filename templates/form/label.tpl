{**
 * templates/form/label.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form label
 *}

{if $FBV_required}
	{fieldLabel name=$FBV_id key=$FBV_label required="true" class='sub_label'}
{else}
	{fieldLabel name=$FBV_id key=$FBV_label class='sub_label'}
{/if}
