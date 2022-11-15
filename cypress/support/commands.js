/**
 * @file cypress/support/commands.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

import '../../lib/pkp/cypress/support/commands';

Cypress.Commands.add('addCategory', (categoryName, categoryPath) => {
	cy.get('div.pkp_grid_category a[id^=component-grid-settings-category-categorycategorygrid-addCategory-button-]').click();
	cy.wait(1000); // Avoid occasional failure due to form init taking time
	cy.get('input[id^="name-en_US-"]').type(categoryName, {delay: 0});
	cy.get('input[id^="path-"]').type(categoryPath, {delay: 0});
	cy.get('form[id=categoryForm]').contains('OK').click();
	cy.wait(2000); // Avoid occasional failure due to form save taking time
});
