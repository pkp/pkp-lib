{**
 * userGroupListbuilderItem.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Represents an item for the user group list builder.
 *}

<option value="{$lbItemId|escape}">{$lbItemName|escape}{if $lbAttributeNames} ({$lbAttributeNames|escape}){/if}</option>

