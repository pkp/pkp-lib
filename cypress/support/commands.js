/**
 * @file cypress/support/commands.js
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 */

import '../../lib/pkp/cypress/support/commands';


Cypress.Commands.add('publish', (issueId, issueTitle) => {
	cy.get('button[id="publication-button"]').click();
	cy.get('button[id="issue-button"]').click();
	cy.get('select[id="journalEntry-issueId-control"]').select(issueId);
	cy.get('div[id="issue"] button:contains("Save")').click();
	cy.get('div:contains("The journal entry details have been updated.")');
	cy.get('div[id="publication"] button:contains("Schedule For Publication")').click();
	cy.get('div:contains("All publication requirements have been met. This will be published immediately in ' + issueTitle + '. Are you sure you want to publish this?")');
	cy.get('div.pkpWorkflow__publishModal button:contains("Publish")').click();
});

Cypress.Commands.add('addCategory', (categoryName, categoryPath) => {
	cy.get('div.pkp_grid_category a[id^=component-grid-settings-category-categorycategorygrid-addCategory-button-]').click();
	cy.wait(1000); // Avoid occasional failure due to form init taking time
	cy.get('input[id^="name-en_US-"]').type(categoryName, {delay: 0});
	cy.get('input[id^="path-"]').type(categoryPath, {delay: 0});
	cy.get('form[id=categoryForm]').contains('OK').click();
	cy.wait(2000); // Avoid occasional failure due to form save taking time
});

Cypress.Commands.add('isInIssue', (submissionTitle, issueTitle) => {
	cy.visit('');
	cy.get('a:contains("Archives")').click();
	cy.get('a:contains("' + issueTitle + '")').click();
	cy.get('a:contains("' + submissionTitle + '")');
});
