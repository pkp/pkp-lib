{**
 * templates/controllers/grid/gridRowSelectInput.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a checkbox that allows users to select a grid row when ticked
 *}
<input type="checkbox" id="select-{$elementId|escape}" name="{$selectName|escape}[]" style="height: 15px; width: 15px;" value="{$elementId|escape}" {if $selected}checked="checked"{/if} />
