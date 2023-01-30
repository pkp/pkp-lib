/**
 * @file cypress/tests/integration/Categories.cy.js
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

 describe('Tests categories in the submission wizard', function() {
	var username = 'catauthor';
	var familyName = 'Fraser'
	var title = 'Test submission wizard with categories';
	var categories = [
		'Applied Science > Computer Science',
		'Applied Science > Engineering',
	];
	
	it('Checks that categories field is not shown in submission wizard', function() {
		cy.register({
			'username': username,
			'givenName': 'Catalin',
			'familyName': familyName,
			'affiliation': 'Public Knowledge Project',
			'country': 'Canada'
		});
		

		cy.contains('Make a New Submission').click();

		// All required fields in the start submission form
		cy.get('input[name="title"]').type(title, {delay: 0});
		cy.get('label:contains("Articles")').click();
		cy.get('label:contains("English")').click();
		cy.get('input[name="submissionRequirements"]').check();
		cy.get('input[name="privacyConsent"]').check();
		cy.contains('Begin Submission').click();

		// The submission wizard has loaded
		cy.get('.pkpSteps__step__label').contains('For the Editors');

		// Go to the For the Editors step
		cy.get('.submissionWizard__footer button').contains('Continue').click();
		cy.get('.submissionWizard__footer button').contains('Continue').click();
		cy.get('.submissionWizard__footer button').contains('Continue').click();

		// Categories field not present
		cy.get('.pkpFormFieldLabel:contains("Categories")').should('not.exist');

		// Categories not visible in review
		cy.get('.submissionWizard__footer button').contains('Continue').click();
		cy.get('.submissionWizard__reviewPanel__item__header:contains("Categories")').should('not.exist');
	});

	it('Enables categories in the submission wizard', function() {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/management/settings/workflow');
		cy.get('button').contains('Metadata').click();
		cy.get('label:contains("Yes, add a categories field to the submission wizard")').click();
		cy.get('#metadata button').contains('Save').click();
		cy.get('#metadata [role="status"]').contains('Saved');
	});

	it('Checks that categories field is shown in submission wizard', function() {
		cy.login(username);
		cy.visit('index.php/publicknowledge/submissions');
		cy.get('a:contains("View ' + familyName + '")').click();

		// The submission wizard has loaded
		cy.get('.pkpSteps__step__label').contains('For the Editors');

		// Go to the For the Editors step
		cy.get('.submissionWizard__footer button').contains('Continue').click();
		cy.get('.submissionWizard__footer button').contains('Continue').click();
		cy.get('.submissionWizard__footer button').contains('Continue').click();

		// Select categories
		categories.forEach((category) => {
			cy.get('label:contains("' + category + '")').click();
		});

		// Categories visible in review
		cy.get('.submissionWizard__footer button').contains('Continue').click();
		cy.get('.submissionWizard__reviewPanel__item__header:contains("Categories")');
		categories.forEach((category) => {
			cy.get('.submissionWizard__reviewPanel__item__value:contains("' + category + '")');
		});
	});
});