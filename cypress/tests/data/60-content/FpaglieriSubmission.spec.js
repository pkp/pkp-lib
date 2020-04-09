/**
 * @file cypress/tests/data/60-content/FpaglieriSubmission.spec.js
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite tests', function() {
	it('Create a submission', function() {
		var title = 'Hansen & Pinto: Reason Reclaimed';
		cy.register({
			'username': 'fpaglieri',
			'givenName': 'Fabio',
			'familyName': 'Paglieri',
			'affiliation': 'University of Rome',
			'country': 'Italy',
		});

		cy.createSubmission({
			'section': 'Reviews',
			title,
			'abstract': 'None.',
		});

		cy.logout();
		cy.findSubmissionAsEditor('dbarnes', null, title);
		cy.get('ul.pkp_workflow_decisions button:contains("Schedule For Publication")').click();
		cy.get('div.pkpPublication button:contains("Schedule For Publication"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to publish this?")');
		cy.get('button:contains("Publish")').click();
	});
});
