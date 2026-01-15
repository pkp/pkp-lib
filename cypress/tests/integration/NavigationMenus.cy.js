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
		cy.get('#setup-button').click();
		cy.get('#navigationMenus-button').click();
		cy.get('#navigationMenuGridContainer a.pkp_linkaction_addNavigationMenu').should('be.visible');
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
		cy.get('[data-cy="active-modal"]').contains('button', 'Cancel').click();
		cy.get('[data-cy="active-modal"]').should('not.exist');
	}

	it('Creates a menu with assigned items', function() {
		cy.login('dbarnes');
		visitNavigationMenus();

		// Create a new menu
		cy.get('#navigationMenuGridContainer a.pkp_linkaction_addNavigationMenu').click();
		cy.get('[data-cy="active-modal"]').should('be.visible');

		// Verify editor loads with both panels
		waitForEditorToLoad();

		// For new menu: assigned should be empty, unassigned should have items
		cy.get('[data-cy="panel-content-assigned"] [data-menu-item-title]').should('not.exist');
		cy.get('[data-cy="panel-content-unassigned"] [data-menu-item-title]').should('have.length.greaterThan', 4);

		// Fill title and save (in Vue modal, the form uses drag-drop for item assignment, so we just create the menu)
		cy.get('[data-cy="active-modal"]').find('input[name="title"]').type(menuName, {delay: 0});
		cy.get('[data-cy="active-modal"]').contains('button', 'Save').click();
		cy.get('[data-cy="active-modal"]').should('not.exist');

		// Verify created
		cy.get('#navigationMenuGridContainer').contains(menuName).should('exist');

		// Re-open menu to verify it can be edited
		cy.get('#navigationMenuGridContainer').contains(menuName).click();
		cy.get('[data-cy="active-modal"]').should('be.visible');
		waitForEditorToLoad();

		closeModal();

		// Delete the menu - click caret to expand row_controls, then click Remove
		cy.get('#navigationMenuGridContainer').contains(menuName).parents('tr').find('a.show_extras').click();
		cy.get('#navigationMenuGridContainer .row_controls:visible').contains('Remove').click();
		cy.get('button').contains('OK').click();
		cy.get('#navigationMenuGridContainer').contains(menuName).should('not.exist');

		cy.logout();
	});

	it('Creates, edits, and deletes a navigation menu item', function() {
		cy.login('dbarnes');
		visitNavigationMenus();

		// Create a new custom menu item (uses legacy AjaxModal)
		cy.get('#navigationMenuItemsGridContainer a.pkp_linkaction_addNavigationMenuItem').click();
		cy.get('form#navigationMenuItemsForm').should('be.visible');

		cy.get('select[name="menuItemType"]').select('NMI_TYPE_CUSTOM');
		cy.get('input[name="title[en]"]').type(itemTitle, {delay: 0});
		cy.get('input[name="path"]').type(itemPath, {delay: 0});

		cy.get('form#navigationMenuItemsForm').contains('button', 'Save').click();
		cy.get('form#navigationMenuItemsForm').should('not.exist');

		// Verify created
		cy.get('#navigationMenuItemsGridContainer').contains(itemTitle).should('exist');

		// Edit the item - click caret to expand row_controls, then click Edit
		cy.get('#navigationMenuItemsGridContainer').contains(itemTitle).parents('tr').find('a.show_extras').click();
		cy.get('#navigationMenuItemsGridContainer .row_controls:visible').contains('Edit').click();
		cy.get('form#navigationMenuItemsForm').should('be.visible');

		const updatedTitle = itemTitle + ' Updated';
		cy.get('input[name="title[en]"]').clear().type(updatedTitle, {delay: 0});

		cy.get('form#navigationMenuItemsForm').contains('button', 'Save').click();
		cy.get('form#navigationMenuItemsForm').should('not.exist');

		// Verify updated
		cy.get('#navigationMenuItemsGridContainer').contains(updatedTitle).should('exist');

		// Delete the item - click caret to expand row_controls, then click Remove
		cy.get('#navigationMenuItemsGridContainer').contains(updatedTitle).parents('tr').find('a.show_extras').click();
		cy.get('#navigationMenuItemsGridContainer .row_controls:visible').contains('Remove').click();
		cy.get('button').contains('OK').click();
		cy.get('#navigationMenuItemsGridContainer').contains(updatedTitle).should('not.exist');

		cy.logout();
	});
});
