/**
 * @file cypress/tests/integration/NavigationMenus.cy.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for Navigation Menu Editor functionality
 */

describe('Navigation Menu Editor', function() {
	const menuName = 'Test Navigation Menu';

	/**
	 * Navigate to the navigation menus tab in website settings
	 */
	function visitNavigationMenus() {
		cy.visit('index.php/publicknowledge/management/settings/website#navigationMenus');
		// Wait for the grid to load
		cy.get('#navigationMenuGridContainer').should('be.visible');
	}

	/**
	 * Open the Add/Edit menu modal
	 * @param {string|null} menuTitle - If provided, edit existing menu; otherwise, add new
	 */
	function openMenuModal(menuTitle = null) {
		if (menuTitle) {
			// Edit existing menu - find row and click edit
			cy.contains('#navigationMenuGridContainer tr', menuTitle)
				.find('a.pkp_linkaction_edit')
				.click();
		} else {
			// Add new menu
			cy.get('#navigationMenuGridContainer a.pkp_linkaction_addNavigationMenu')
				.click();
		}
		// Wait for modal to open
		cy.get('.pkp_modal').should('be.visible');
	}

	/**
	 * Fill in menu form fields
	 * @param {string} title - Menu title
	 */
	function fillMenuForm(title) {
		cy.get('input[name="title[en]"]').clear().type(title, {delay: 0});
	}

	/**
	 * Save the menu form
	 */
	function saveMenuForm() {
		cy.get('.pkp_modal form button[type="submit"]').click();
		// Wait for modal to close or success message
		cy.get('.pkp_modal').should('not.exist');
	}

	describe('Create Navigation Menu', function() {
		it('Opens the add menu modal for new menu', function() {
			cy.login('dbarnes');
			visitNavigationMenus();
			openMenuModal();

			// Verify the modal is open and editor is displayed
			cy.get('.pkp_modal').should('be.visible');
			cy.get('input[name="title[en]"]').should('exist');

			// Verify the NavigationMenuEditor component is loaded
			cy.get('[data-cy="navigation-menu-editor"]').should('exist');

			// Verify panels are displayed using data-cy attributes
			cy.get('[data-cy="panel-assigned"]').should('exist');
			cy.get('[data-cy="panel-unassigned"]').should('exist');

			// For a new menu, assigned panel should be empty (no menu items)
			cy.get('[data-cy="panel-content-assigned"] [data-menu-item-title]').should('not.exist');

			// Unassigned panel should have items
			cy.get('[data-cy="panel-content-unassigned"] [data-menu-item-title]').should('have.length.greaterThan', 0);

			// Close modal without saving
			cy.get('.pkp_modal_closeButton').click();
			cy.logout();
		});

		it('Creates a new navigation menu', function() {
			cy.login('dbarnes');
			visitNavigationMenus();
			openMenuModal();

			// Fill in the form
			fillMenuForm(menuName);

			// Verify unassigned items are available
			cy.get('[data-cy="panel-content-unassigned"] [data-menu-item-title]')
				.should('have.length.greaterThan', 0);

			// Save the menu
			saveMenuForm();

			// Verify the menu was created
			cy.contains('#navigationMenuGridContainer tr', menuName).should('exist');
			cy.logout();
		});
	});

	describe('Edit Navigation Menu', function() {
		it('Opens existing menu for editing', function() {
			cy.login('dbarnes');
			visitNavigationMenus();

			// Find and edit the test menu we created
			openMenuModal(menuName);

			// Verify the modal is open with the menu data
			cy.get('input[name="title[en]"]').should('have.value', menuName);

			// Verify the NavigationMenuEditor component is loaded
			cy.get('[data-cy="navigation-menu-editor"]').should('exist');

			// Close modal
			cy.get('.pkp_modal_closeButton').click();
			cy.logout();
		});

		it('Displays both panels correctly', function() {
			cy.login('dbarnes');
			visitNavigationMenus();
			openMenuModal(menuName);

			// Check both panels exist with their titles
			cy.get('[data-cy="panel-assigned"]').should('exist');
			cy.get('[data-cy="panel-unassigned"]').should('exist');

			// Assigned panel should show "Assigned" title
			cy.get('[data-cy="panel-assigned"]').contains('Assigned');

			// Unassigned panel should show "Unassigned" title
			cy.get('[data-cy="panel-unassigned"]').contains('Unassigned');

			cy.get('.pkp_modal_closeButton').click();
			cy.logout();
		});
	});

	describe('Navigation Menu Editor Interactions', function() {
		it('Shows menu items in the panels', function() {
			cy.login('dbarnes');
			visitNavigationMenus();
			openMenuModal(menuName);

			// Check that menu items have proper structure
			cy.get('[data-cy="panel-content-unassigned"] [data-menu-item-title]')
				.first()
				.should('be.visible');

			cy.get('.pkp_modal_closeButton').click();
			cy.logout();
		});

		it('Menu items have visibility indicators', function() {
			cy.login('dbarnes');
			visitNavigationMenus();
			openMenuModal(menuName);

			// Check that items in unassigned panel exist
			cy.get('[data-cy="panel-content-unassigned"] [data-menu-item-title]')
				.should('have.length.greaterThan', 0);

			cy.get('.pkp_modal_closeButton').click();
			cy.logout();
		});
	});

	describe('Delete Navigation Menu', function() {
		it('Deletes the test navigation menu', function() {
			cy.login('dbarnes');
			visitNavigationMenus();

			// Find and delete the test menu
			cy.contains('#navigationMenuGridContainer tr', menuName)
				.find('a.pkp_linkaction_delete')
				.click();

			// Confirm deletion
			cy.get('.pkp_modal_confirmation button').contains('OK').click();

			// Verify the menu was deleted
			cy.contains('#navigationMenuGridContainer tr', menuName).should('not.exist');
			cy.logout();
		});
	});
});

describe('Navigation Menu Items', function() {
	const itemTitle = 'Test Custom Item';
	const itemPath = 'test-custom-item';

	/**
	 * Navigate to the navigation menus tab
	 */
	function visitNavigationMenus() {
		cy.visit('index.php/publicknowledge/management/settings/website#navigationMenus');
		cy.get('#navigationMenuItemsGridContainer').should('be.visible');
	}

	/**
	 * Open add/edit item modal
	 * @param {string|null} title - If provided, edit existing; otherwise, add new
	 */
	function openItemModal(title = null) {
		if (title) {
			cy.contains('#navigationMenuItemsGridContainer tr', title)
				.find('a.pkp_linkaction_edit')
				.click();
		} else {
			cy.get('#navigationMenuItemsGridContainer a.pkp_linkaction_addNavigationMenuItem')
				.click();
		}
		cy.get('.pkp_modal').should('be.visible');
	}

	it('Creates a new custom navigation menu item', function() {
		cy.login('dbarnes');
		visitNavigationMenus();
		openItemModal();

		// Select custom type
		cy.get('select[name="menuItemType"]').select('NMI_TYPE_CUSTOM');

		// Fill in details
		cy.get('input[name="title[en]"]').type(itemTitle, {delay: 0});
		cy.get('input[name="path"]').type(itemPath, {delay: 0});

		// Save
		cy.get('.pkp_modal form button[type="submit"]').click();
		cy.get('.pkp_modal').should('not.exist');

		// Verify item was created
		cy.contains('#navigationMenuItemsGridContainer tr', itemTitle).should('exist');
		cy.logout();
	});

	it('Edits an existing navigation menu item', function() {
		cy.login('dbarnes');
		visitNavigationMenus();
		openItemModal(itemTitle);

		// Update title
		const updatedTitle = itemTitle + ' Updated';
		cy.get('input[name="title[en]"]').clear().type(updatedTitle, {delay: 0});

		// Save
		cy.get('.pkp_modal form button[type="submit"]').click();
		cy.get('.pkp_modal').should('not.exist');

		// Verify update
		cy.contains('#navigationMenuItemsGridContainer tr', updatedTitle).should('exist');
		cy.logout();
	});

	it('Deletes a navigation menu item', function() {
		cy.login('dbarnes');
		visitNavigationMenus();

		const updatedTitle = itemTitle + ' Updated';

		// Delete the item
		cy.contains('#navigationMenuItemsGridContainer tr', updatedTitle)
			.find('a.pkp_linkaction_delete')
			.click();

		// Confirm
		cy.get('.pkp_modal_confirmation button').contains('OK').click();

		// Verify deletion
		cy.contains('#navigationMenuItemsGridContainer tr', updatedTitle).should('not.exist');
		cy.logout();
	});
});

describe('Navigation Menu Editor - New Menu with Items', function() {
	const menuName = 'Menu With Items Test';

	/**
	 * Navigate to the navigation menus tab
	 */
	function visitNavigationMenus() {
		cy.visit('index.php/publicknowledge/management/settings/website#navigationMenus');
		cy.get('#navigationMenuGridContainer').should('be.visible');
	}

	it('Verifies new menu shows all items as unassigned', function() {
		cy.login('dbarnes');
		visitNavigationMenus();

		// Open add menu modal
		cy.get('#navigationMenuGridContainer a.pkp_linkaction_addNavigationMenu').click();
		cy.get('.pkp_modal').should('be.visible');

		// Verify the NavigationMenuEditor loaded correctly
		cy.get('[data-cy="navigation-menu-editor"]').should('exist');

		// For new menu: assigned panel should be empty
		cy.get('[data-cy="panel-content-assigned"] [data-menu-item-title]').should('not.exist');

		// For new menu: unassigned panel should have all available items
		cy.get('[data-cy="panel-content-unassigned"] [data-menu-item-title]')
			.should('have.length.greaterThan', 0);

		// Close without saving
		cy.get('.pkp_modal_closeButton').click();
		cy.logout();
	});

	it('Creates menu and verifies it can be edited', function() {
		cy.login('dbarnes');
		visitNavigationMenus();

		// Create the menu
		cy.get('#navigationMenuGridContainer a.pkp_linkaction_addNavigationMenu').click();
		cy.get('.pkp_modal').should('be.visible');

		cy.get('input[name="title[en]"]').type(menuName, {delay: 0});
		cy.get('.pkp_modal form button[type="submit"]').click();
		cy.get('.pkp_modal').should('not.exist');

		// Verify menu was created
		cy.contains('#navigationMenuGridContainer tr', menuName).should('exist');

		// Now edit it
		cy.contains('#navigationMenuGridContainer tr', menuName)
			.find('a.pkp_linkaction_edit')
			.click();
		cy.get('.pkp_modal').should('be.visible');

		// Verify editor loads for existing menu
		cy.get('[data-cy="navigation-menu-editor"]').should('exist');

		cy.get('.pkp_modal_closeButton').click();

		// Clean up - delete the menu
		cy.contains('#navigationMenuGridContainer tr', menuName)
			.find('a.pkp_linkaction_delete')
			.click();
		cy.get('.pkp_modal_confirmation button').contains('OK').click();
		cy.contains('#navigationMenuGridContainer tr', menuName).should('not.exist');

		cy.logout();
	});
});
