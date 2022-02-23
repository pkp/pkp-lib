/**
 * @file cypress/tests/data/60-content/CkwantesSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite tests', function() {
	it('Create a submission', function() {
		var title = 'The Facets Of Job Satisfaction: A Nine-Nation Comparative Study Of Construct Equivalence';
		cy.register({
			'username': 'ckwantes',
			'givenName': 'Catherine',
			'familyName': 'Kwantes',
			'affiliation': 'University of Windsor',
			'country': 'Canada'
		});

		cy.createSubmission({
			title,
			'abstract': 'Archival data from an attitude survey of employees in a single multinational organization were used to examine the degree to which national culture affects the nature of job satisfaction. Responses from nine countries were compiled to create a benchmark against which nations could be individually compared. Factor analysis revealed four factors: Organizational Communication, Organizational Efficiency/Effectiveness, Organizational Support, and Personal Benefit. Comparisons of factor structures indicated that Organizational Communication exhibited the most construct equivalence, and Personal Benefit the least. The most satisfied employees were those from China, and the least satisfied from Brazil, consistent with previous findings that individuals in collectivistic nations report higher satisfaction. The research findings suggest that national cultural context exerts an effect on the nature of job satisfaction.',
			'keywords': [
				'employees',
				'survey'
			]
		});

		cy.get('a').contains('Review this submission').click();

		// Edit metadata
		cy.get('button#metadata-button').click();
		cy.get('#metadata-keywords-control-en_US').type('multinational', {delay: 0});
		cy.wait(500);
		cy.get('#metadata-keywords-control-en_US').type('{enter}', {delay: 0});
		cy.get('#metadata button').contains('Save').click();
		cy.get('#metadata-keywords-selected-en_US').contains('multinational');
		cy.get('#metadata [role="status"]').contains('Saved');

		// Edit Contributors
		cy.wait(1500);
		cy.get('button#contributors-button').click();
		cy.get('#contributors button').contains('Add Contributor').click();
		cy.get('#contributors [name="givenName-en_US"]').type('Urho', {delay: 0});
		cy.get('#contributors [name="familyName-en_US"]').type('Kekkonen', {delay: 0});
		cy.get('#contributors [name="country"]').select('Finland');
		cy.get('#contributors [name="email"]').type('ukk@mailinator.com', {delay: 0});
		cy.get('#contributors [name="affiliation-en_US"]').type('Academy of Finland', {delay: 0});
		cy.get('#contributors button').contains('Save').click();
		cy.wait(500);
		cy.get('#contributors div').contains('Urho Kekkonen');

		// Edit title
		cy.get('button#titleAbstract-button').click();
		cy.get('input[id^="titleAbstract-title-control-en_US"').clear()
		cy.get('input[id^="titleAbstract-title-control-en_US"').type('The Facets Of Job Satisfaction', {delay: 0});
		cy.get('input[id^="titleAbstract-subtitle-control-en_US"').type('A Nine-Nation Comparative Study Of Construct Equivalence', {delay: 0});
		cy.get('#titleAbstract button').contains('Save').click();
		cy.get('#titleAbstract [role="status"]').contains('Saved');
		cy.wait(500);

		cy.logout();
		});

	it('Publish submission', function() {
		cy.findSubmissionAsEditor('dbarnes', null, 'Kwantes');
		cy.get('.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});
})
