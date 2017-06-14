{**
 * templates/controllers/grid/navigationMenus/navigationMenuItemsList.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display list of navigation menu items for a given navigation menu.
 *}

<div id="possibleParentNavigationMemuItemsDiv">
    {fbvElement type="select" id="assoc_id" from=$navigationMenuItems selected=$parentNavigationMenuItemId label="manager.navigationMenus.form.navigationMenuItemsTitle" translate=false}
</div>
