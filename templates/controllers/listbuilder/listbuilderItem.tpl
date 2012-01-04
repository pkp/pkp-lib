{**
 * listbuilderItem.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Represents an item in a listbuilder source (for a drop-down list or autocomplete source)
 *}

<option value='{$itemId}'>{$itemName}{if $attributeNames} ({$attributeNames}){/if}</option>
