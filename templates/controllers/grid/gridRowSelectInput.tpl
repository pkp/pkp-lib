{**
 * gridRowSelectInput.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a checkbox that also selects a grid row when ticked
 *}
<input type="checkbox" id="select-{$rowId}" name="{if $selectName}{$selectName}{else}selectedFiles{/if}[]" value={$rowId} class="reviewFilesSelect field checkbox" {if $selectedFileIds && in_array($rowId, $selectedFileIds)}checked{/if} />
