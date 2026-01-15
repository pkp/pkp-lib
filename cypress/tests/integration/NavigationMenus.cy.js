/**
 * @file cypress/tests/integration/NavigationMenus.cy.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for Navigation Menu Editor functionality
 */

describe('Navigation Menu Management', function() {
	const menuName = 'Test Navigation Menu';
	const itemTitle = 'Test Custom Item';
	const itemPath = 'test-custom-item';

	/**
	 * Navigate to the navigation section in website settings
	 */
	function visitNavigationMenus() {
		cy.visit('index.php/publicknowledge/management/settings/website#setup');
		cy.contains('button', 'Setup').click();
		cy.contains('button', 'Navigation').click();
		cy.contains('Add Menu').should('be.visible');
	}

	/**
	 * Wait for the NavigationMenuEditor Vue component to finish loading
	 */
	function waitForEditorToLoad() {
		cy.get('[data-cy="navigation-menu-editor"]', {timeout: 20000}).should('exist');
		cy.get('[data-cy="assigned-panel"]').should('exist');
		cy.get('[data-cy="unassigned-panel"]').should('exist');
	}

	/**
	 * Close the Vue modal by clicking the Cancel button
	 */
	function closeModal() {
		cy.get('[data-cy="active-modal"]').within(() => {
			cy.contains('button', 'Cancel').click();
		});
		cy.get('[data-cy="active-modal"]').should('not.exist');
	}

	/**
	 * Expand the row actions for a grid row containing the specified text
	 * Clicks the chevron/arrow to show row controls (uses class since there's no text)
	 */
	function expandRowActions(gridSelector, rowText) {
		cy.get(gridSelector).contains(rowText).parents('tr').find('a.show_extras').click();
	}

	it('Creates a menu with assigned items', function() {
		cy.login('dbarnes');
		visitNavigationMenus();

		// Create a new menu
		cy.contains('Add Menu').click();
		cy.get('[data-cy="active-modal"]').should('be.visible');

		// Verify editor loads with both panels
		waitForEditorToLoad();

		// For new menu: assigned should be empty, unassigned should have items
		cy.get('[data-cy="panel-content-assigned"] [data-menu-item-title]').should('not.exist');
		cy.get('[data-cy="panel-content-unassigned"] [data-menu-item-title]').should('have.length.greaterThan', 4);

		// Fill title and save
		cy.get('[data-cy="active-modal"]').within(() => {
			cy.get('input[name="title"]').type(menuName, {delay: 0});
			cy.contains('button', 'Save').click();
		});
		cy.get('[data-cy="active-modal"]').should('not.exist');

		// Verify created
		cy.get('#navigationMenuGridContainer').contains(menuName).should('exist');

		// Re-open menu to verify it can be edited
		cy.get('#navigationMenuGridContainer').contains(menuName).click();
		cy.get('[data-cy="active-modal"]').should('be.visible');
		waitForEditorToLoad();

		closeModal();

		// Delete the menu - expand row actions, then click Remove
		expandRowActions('#navigationMenuGridContainer', menuName);
		cy.get('#navigationMenuGridContainer .row_controls:visible').contains('Remove').click();
		cy.contains('button', 'OK').click();
		cy.get('#navigationMenuGridContainer').contains(menuName).should('not.exist');

		cy.logout();
	});

	it('Creates, edits, and deletes a navigation menu item', function() {
		cy.login('dbarnes');
		visitNavigationMenus();

		// Create a new custom menu item (uses legacy AjaxModal)
		cy.contains('Add item').click();
		cy.get('form#navigationMenuItemsForm').should('be.visible');

		cy.get('form#navigationMenuItemsForm').within(() => {
			cy.get('select[name="menuItemType"]').select('NMI_TYPE_CUSTOM');
			cy.get('input[name="title[en]"]').type(itemTitle, {delay: 0});
			cy.get('input[name="path"]').type(itemPath, {delay: 0});
			cy.contains('button', 'Save').click();
		});
		cy.get('form#navigationMenuItemsForm').should('not.exist');

		// Verify created
		cy.get('#navigationMenuItemsGridContainer').contains(itemTitle).should('exist');

		// Edit the item - expand row actions, then click Edit
		expandRowActions('#navigationMenuItemsGridContainer', itemTitle);
		cy.get('#navigationMenuItemsGridContainer .row_controls:visible').contains('Edit').click();
		cy.get('form#navigationMenuItemsForm').should('be.visible');

		const updatedTitle = itemTitle + ' Updated';
		cy.get('form#navigationMenuItemsForm').within(() => {
			cy.get('input[name="title[en]"]').clear().type(updatedTitle, {delay: 0});
			cy.contains('button', 'Save').click();
		});
		cy.get('form#navigationMenuItemsForm').should('not.exist');

		// Verify updated
		cy.get('#navigationMenuItemsGridContainer').contains(updatedTitle).should('exist');

		// Delete the item - expand row actions, then click Remove
		expandRowActions('#navigationMenuItemsGridContainer', updatedTitle);
		cy.get('#navigationMenuItemsGridContainer .row_controls:visible').contains('Remove').click();
		cy.contains('button', 'OK').click();
		cy.get('#navigationMenuItemsGridContainer').contains(updatedTitle).should('not.exist');

		cy.logout();
	});
});
