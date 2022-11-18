/**
 * @file cypress/tests/data/60-content/FpaglieriSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite: Fpaglieri', function() {
	let submission;

	before(function() {
		const title = 'Hansen & Pinto: Reason Reclaimed';
		submission = {
			id: 0,
			section: 'Preprints',
			prefix: '',
			title: title,
			subtitle: '',
			abstract: 'None.',
			shortAuthorString: 'Paglieri',
			authorNames: ['Fabio Paglieri'],
			sectionId: 1,
			assignedAuthorNames: ['Fabio Paglieri'],
			authors: [
				{
					givenName: 'Fabio',
					familyName: 'Paglieri',
					email: 'fpaglieri@mailinator.com',
					country: 'Italy',
					affiliation: 'University of Rome'
				}
			],
			files: [
				{
					'file': 'dummy.pdf',
					'fileName': title + '.pdf',
					'mimeType': 'application/pdf',
					'genre': Cypress.env('defaultGenre')
				},
			]
		};
	});

	it('Create a submission', function() {
		cy.register({
			'username': 'fpaglieri',
			'givenName': 'Fabio',
			'familyName': 'Paglieri',
			'affiliation': 'University of Rome',
			'country': 'Italy',
		});

		// Go to page where CSRF token is available
		cy.visit('/index.php/publicknowledge/user/profile');

		let csrfToken = '';
		cy.window()
			.then((win) => {
				csrfToken = win.pkp.currentUser.csrfToken;
			})
			.then(() => {
				return cy.createSubmissionWithApi(submission, csrfToken);
			})
			.then(xhr => {
				return cy.submitSubmissionWithApi(submission.id, csrfToken);
			});

		cy.logout();
		cy.findSubmissionAsEditor('dbarnes', null, 'Paglieri');
		cy.get('.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});
});
