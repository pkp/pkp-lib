/**
 * @file cypress/support/commands_orcid.js
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

Cypress.Commands.add('enableOrcid', (language, contextPath) => {
	cy.login('admin', 'admin');

	cy.visit('index.php/publicknowledge/management/settings/access');
	cy.get('#orcidSettings-button').should('exist').click();

	// Check that the checkbox to enable ORCID is visible, and select it
	cy.get('input[name^="orcidEnabled"]').should('be.visible').check();

	// Check that the form fields are visible
	cy.get('select[name="orcidApiType"]')
		.should('be.visible')
		.select('memberSandbox');
	cy.get('input[name="orcidClientId"]')
		.should('be.visible')
		.clear()
		.type('TEST_CLIENT_ID', {delay: 0});
	cy.get('input[name="orcidClientSecret"]')
		.should('be.visible')
		.clear()
		.type('TEST_SECRET', {delay: 0});
	cy.get('input[name="orcidCity"]').should('be.visible');
	cy.get('input[name="orcidSendMailToAuthorsOnPublication"]')
		.should('be.visible')
		.check();
	cy.get('select[name="orcidLogLevel"]').should('be.visible').select('INFO');
	cy.get('button:contains("Save")').eq(1).should('be.visible').click();

	cy.reload();

	cy.get('input[name="orcidClientId"]')
		.should('be.visible')
		.should('have.value', 'TEST_CLIENT_ID');
});
