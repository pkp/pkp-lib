/**
 * @file cypress/tests/integration/Multilingual.cy.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */
describe('Multilingual configurations', function() {
	it('Tests when locale is active for Forms and Submissions but not UI', function() {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/management/settings/website');
		cy.get('button').contains('Setup').eq(0).click();
		cy.get('button').contains('Languages').click();
		cy.get('input[id^="select-cell-fr_CA-uiLocale').uncheck();
		cy.contains('Locale settings saved.');

		cy.visit('index.php/publicknowledge/management/settings/context');
		cy.get('button.pkpFormLocales__locale').eq(0).contains('French').click();
		cy.get('#masthead-acronym-control-fr_CA').type('JCP');
		cy.get('#masthead button').contains('Save').click();
		cy.get('#masthead [role="status"]').contains('Saved');

		cy.visit('index.php/publicknowledge/workflow/access/1');
		// latest publication is opened by default on a submission, click its Title & Abstract link
		cy.get('[data-cy="active-modal"] nav a:contains("Title & Abstract")').last().click();
		cy.get('button.pkpFormLocales__locale').eq(0).contains('French').click();
		cy.get('#titleAbstract-title-control-fr_CA').type("L'influence de la lactation sur la quantité et la qualité de la production de cachemire", {force: true});
		cy.get('button').contains('Save').click();
		cy.get('[role="status"]').contains('Saved');

		// Re-enable French in UI
		cy.visit('index.php/publicknowledge/management/settings/website');
		cy.get('button').contains('Setup').eq(0).click();
		cy.get('button').contains('Languages').click();
		cy.get('input[id^="select-cell-fr_CA-uiLocale').check();
		cy.contains('Locale settings saved.');
	});
});
