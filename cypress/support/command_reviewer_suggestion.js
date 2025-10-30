/**
 * @file cypress/support/command_reviewer_suggestion.js
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

Cypress.Commands.add('enableReviewerSuggestion', () => {
	cy.login('admin', 'admin');

	cy.visit('index.php/publicknowledge/management/settings/workflow');
    cy.get('button#review-button').should('exist').click();

	// Check that the checkbox to enable Reviewer Suggestion is visible, unchecked, and select it
	cy.get('input[name="reviewerSuggestionEnabled"]').should('be.visible').should('not.be.checked').check();

    cy.get('button:contains("Save")').eq(3).should('be.visible').click();
    cy.wait(2000);
    cy.reload();

    cy.get('input[name="reviewerSuggestionEnabled"]').should('be.visible').should('be.checked');
    cy.logout();
});

Cypress.Commands.add('disableReviewerSuggestion', () => {
	cy.login('admin', 'admin');

	cy.visit('index.php/publicknowledge/management/settings/workflow');
    cy.get('button#review-button').should('exist').click();

	// Check that the checkbox to enable Reviewer Suggestion is visible, checked, and unselect it
	cy.get('input[name="reviewerSuggestionEnabled"]').should('be.visible').should('be.checked').uncheck();

	cy.get('button:contains("Save")').eq(3).should('be.visible').click();
    cy.wait(2000);
    cy.reload();

    cy.get('input[name="reviewerSuggestionEnabled"]').should('be.visible').should('not.be.checked');
    cy.logout();
});

Cypress.Commands.add('assertReviewerSuggestionsCount', (count) => {
    cy.get('[data-cy="reviewer-suggestion-manager"]')
        .find('ul > li')
        .should('have.length', count);
});
