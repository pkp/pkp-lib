{**
 * gridRowSelectInput.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a checkbox that allows users to select a grid row when ticked
 *}
<input type="checkbox" id="select-{$elementId}" name="{$selectName}[]" value={$elementId} class="field checkbox" {if $selected}checked="checked"{/if} />
