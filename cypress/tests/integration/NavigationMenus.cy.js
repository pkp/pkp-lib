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
	 * Close the modal by clicking the Cancel button
	 */
	function closeModal() {
		cy.get('form#navigationMenuForm a.cancelButton').click();
		cy.get('form#navigationMenuForm').should('not.exist');
	}

	/**
	 * Assign menu items by adding hidden form fields directly
	 * This bypasses drag-and-drop UI since Cypress can't simulate it natively
	 * @param {Array} itemConfigs - Array of {id, seq, parentId} objects
	 */
	function assignItemsViaFormFields(itemConfigs) {
		cy.get('form#navigationMenuForm').then($form => {
			const form = $form[0];
			itemConfigs.forEach(({id, seq, parentId}) => {
				// Create seq field
				const seqField = document.createElement('input');
				seqField.type = 'hidden';
				seqField.name = `menuTree[${id}][seq]`;
				seqField.value = seq;
				form.appendChild(seqField);

				// Create parentId field if nested
				if (parentId !== null && parentId !== undefined) {
					const parentField = document.createElement('input');
					parentField.type = 'hidden';
					parentField.name = `menuTree[${id}][parentId]`;
					parentField.value = parentId;
					form.appendChild(parentField);
				}
			});
		});
	}

	it('Creates a menu with assigned items', function() {
		cy.login('dbarnes');
		visitNavigationMenus();

		// Create a new menu
		cy.get('#navigationMenuGridContainer a.pkp_linkaction_addNavigationMenu').click();
		cy.get('form#navigationMenuForm').should('be.visible');

		// Verify editor loads with both panels
		waitForEditorToLoad();

		// For new menu: assigned should be empty, unassigned should have items
		cy.get('[data-cy="panel-content-assigned"] [data-menu-item-title]').should('not.exist');
		cy.get('[data-cy="panel-content-unassigned"] [data-menu-item-title]').should('have.length.greaterThan', 4);

		// Get IDs of first 5 unassigned items from their data-cy attributes
		const itemIds = [];
		cy.get('[data-cy="panel-content-unassigned"] [data-cy^="menu-item-"]')
			.each(($el, index) => {
				if (index < 5) {
					const dataCy = $el.attr('data-cy');
					const id = dataCy.replace('menu-item-', '');
					itemIds.push(id);
				}
			})
			.then(() => {
				// Assign 5 items with one nested 3 levels deep:
				// Item 0 (level 1)
				//   Item 2 (level 2)
				//     Item 3 (level 3)
				// Item 1 (level 1)
				// Item 4 (level 1)
				assignItemsViaFormFields([
					{id: itemIds[0], seq: 0, parentId: null},      // Level 1
					{id: itemIds[2], seq: 0, parentId: itemIds[0]}, // Level 2 (child of 0)
					{id: itemIds[3], seq: 0, parentId: itemIds[2]}, // Level 3 (child of 2)
					{id: itemIds[1], seq: 1, parentId: null},      // Level 1
					{id: itemIds[4], seq: 2, parentId: null},      // Level 1
				]);

				// Fill title and save
				cy.get('input[name="title"]').type(menuName, {delay: 0});
				cy.get('form#navigationMenuForm button[type="submit"]').click();
				cy.get('form#navigationMenuForm').should('not.exist');

				// Verify created
				cy.get('#navigationMenuGridContainer').contains(menuName).should('exist');

				// Re-open menu to verify items were saved
				cy.get('#navigationMenuGridContainer').contains(menuName).click();
				cy.get('form#navigationMenuForm').should('be.visible');
				waitForEditorToLoad();

				// Verify 5 items are now in assigned panel
				cy.get('[data-cy="panel-content-assigned"] [data-menu-item-title]').should('have.length', 5);

				closeModal();

				// Delete the menu - click caret to expand row_controls, then click Remove
				cy.get('#navigationMenuGridContainer').contains(menuName).parents('tr').find('a.show_extras').click();
				cy.get('#navigationMenuGridContainer .row_controls:visible').contains('Remove').click();
				cy.get('button').contains('OK').click();
				cy.get('#navigationMenuGridContainer').contains(menuName).should('not.exist');
			});

		cy.logout();
	});

	it('Creates, edits, and deletes a navigation menu item', function() {
		cy.login('dbarnes');
		visitNavigationMenus();

		// Create a new custom menu item
		cy.get('#navigationMenuItemsGridContainer a.pkp_linkaction_addNavigationMenuItem').click();
		cy.get('form#navigationMenuItemsForm').should('be.visible');

		cy.get('select[name="menuItemType"]').select('NMI_TYPE_CUSTOM');
		cy.get('input[name="title[en]"]').type(itemTitle, {delay: 0});
		cy.get('input[name="path"]').type(itemPath, {delay: 0});

		cy.get('form#navigationMenuItemsForm button[type="submit"]').click();
		cy.get('form#navigationMenuItemsForm').should('not.exist');

		// Verify created
		cy.get('#navigationMenuItemsGridContainer').contains(itemTitle).should('exist');

		// Edit the item - click caret to expand row_controls, then click Edit
		cy.get('#navigationMenuItemsGridContainer').contains(itemTitle).parents('tr').find('a.show_extras').click();
		cy.get('#navigationMenuItemsGridContainer .row_controls:visible').contains('Edit').click();
		cy.get('form#navigationMenuItemsForm').should('be.visible');

		const updatedTitle = itemTitle + ' Updated';
		cy.get('input[name="title[en]"]').clear().type(updatedTitle, {delay: 0});

		cy.get('form#navigationMenuItemsForm button[type="submit"]').click();
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
