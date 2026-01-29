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

	beforeEach(function() {
		cy.login('dbarnes');
		visitNavigationMenus();
	});

	afterEach(function() {
		cy.logout();
	});

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
		cy.contains('[data-cy="active-modal"] button', 'Cancel').click();
		cy.get('[data-cy="active-modal"]').should('not.exist');
	}

	/**
	 * Expand the row actions for a grid row containing the specified text
	 */
	function expandRowActions(gridSelector, rowText) {
		cy.get(gridSelector).contains(rowText).parents('tr').find('a.show_extras').click();
	}

	/**
	 * Create a navigation menu with optional area assignment
	 */
	function createMenu(title, area = null) {
		cy.contains('Add Menu').click();
		waitForEditorToLoad();
		cy.get('[data-cy="active-modal"] input[name="title"]').type(title, {delay: 0});
		if (area !== null) {
			cy.get('[data-cy="active-modal"] select[name="areaName"]').select(area);
		}
		cy.contains('[data-cy="active-modal"] button', 'Save').click();
	}

	/**
	 * Delete a navigation menu by title
	 */
	function deleteMenu(title) {
		expandRowActions('#navigationMenuGridContainer', title);
		cy.get('#navigationMenuGridContainer .row_controls:visible').contains('Remove').click();
		cy.contains('button', 'OK').click();
		cy.get('#navigationMenuGridContainer').contains(title).should('not.exist');
	}

	it('Creates navigation menu, validates duplicates, and handles area assignment', function() {
		// 1. Create a new menu and verify editor functionality
		cy.contains('Add Menu').click();
		waitForEditorToLoad();

		// For new menu: assigned should be empty, unassigned should have items
		cy.get('[data-cy="assigned-panel"] [data-menu-item-title]').should('not.exist');
		cy.get('[data-cy="unassigned-panel"] [data-menu-item-title]').should('have.length.greaterThan', 4);
		cy.get('[data-cy="unassigned-panel"]').contains('Register').should('exist');
		cy.get('[data-cy="unassigned-panel"]').contains('Login').should('exist');

		// Fill title and save (without area initially)
		cy.get('[data-cy="active-modal"] input[name="title"]').type(menuName, {delay: 0});
		cy.contains('[data-cy="active-modal"] button', 'Save').click();
		cy.get('[data-cy="active-modal"]').should('not.exist');
		cy.get('#navigationMenuGridContainer').contains(menuName).should('exist');

		// 2. Edit the menu title
		const updatedMenuName = `${menuName} Updated`;
		cy.get('#navigationMenuGridContainer').contains(menuName).click();
		waitForEditorToLoad();
		cy.get('[data-cy="active-modal"] input[name="title"]').clear().type(updatedMenuName, {delay: 0});
		cy.contains('[data-cy="active-modal"] button', 'Save').click();
		cy.get('[data-cy="active-modal"]').should('not.exist');
		cy.get('#navigationMenuGridContainer').contains(updatedMenuName).should('exist');

		// 3. Test duplicate title validation
		createMenu(updatedMenuName);
		cy.get('[data-cy="active-modal"]').contains('This title already exists').should('exist');
		closeModal();

		// 4. Test area already assigned validation
		// Primary Navigation Menu uses "primary" area by default
		createMenu(`${menuName} 2`, 'primary');
		cy.get('[data-cy="active-modal"]').contains('A navigation menu is already assigned to this area').should('exist');
		closeModal();

		// Cleanup
		deleteMenu(updatedMenuName);
	});

	it('Creates, edits, and deletes a navigation menu item', function() {
		// Create a new custom menu item
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

		// Edit the item
		expandRowActions('#navigationMenuItemsGridContainer', itemTitle);
		cy.get('#navigationMenuItemsGridContainer .row_controls:visible').contains('Edit').click();
		cy.get('form#navigationMenuItemsForm').should('be.visible');

		const updatedTitle = `${itemTitle} Updated`;
		cy.get('form#navigationMenuItemsForm').within(() => {
			cy.get('input[name="title[en]"]').clear().type(updatedTitle, {delay: 0});
			cy.contains('button', 'Save').click();
		});
		cy.get('form#navigationMenuItemsForm').should('not.exist');

		// Verify updated
		cy.get('#navigationMenuItemsGridContainer').contains(updatedTitle).should('exist');

		// Delete the item
		expandRowActions('#navigationMenuItemsGridContainer', updatedTitle);
		cy.get('#navigationMenuItemsGridContainer .row_controls:visible').contains('Remove').click();
		cy.contains('button', 'OK').click();
		cy.get('#navigationMenuItemsGridContainer').contains(updatedTitle).should('not.exist');
	});
});
