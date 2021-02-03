{**
 * templates/controllers/grid/users/userSelect/userSelectRadioButton.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display a radio button for selecting one user from the grid
 *}

<input type="radio" id="user_{$rowId}" name="userId" class="advancedUserSelect" {if $userId==$rowId}checked="checked" {/if}value="{$rowId}" />
