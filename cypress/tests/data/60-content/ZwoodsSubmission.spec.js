/**
 * @file cypress/tests/data/60-content/ZwoodsSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite: Zwoods', function() {
	let submission;

	before(function() {
		const title = 'Finocchiaro: Arguments About Arguments';
		submission = {
			id: 0,
			section: 'Preprints',
			prefix: '',
			title: title,
			subtitle: '',
			abstract: 'None.',
			shortAuthorString: 'Woods',
			authorNames: ['Zita Woods'],
			sectionId: 1,
			assignedAuthorNames: ['Zita Woods'],
			authors: [
				{
					givenName: 'Zita',
					familyName: 'Woods',
					email: 'zwoods@mailinator.com',
					country: 'United States',
					affiliation: 'CUNY'
				}
			],
			files: [
				{
					'file': 'dummy.pdf',
					'fileName': title + '.pdf',
					'mimeType': 'application/pdf',
					'genre': Cypress.env('defaultGenre')
				},
			],
			keywords: [
				'education',
				'citizenship'
			]
		};
	});

	it('Create a submission', function() {
		cy.register({
			'username': 'zwoods',
			'givenName': 'Zita',
			'familyName': 'Woods',
			'affiliation': 'CUNY',
			'country': 'United States',
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
		cy.findSubmissionAsEditor('dbarnes', null, 'Woods');
		cy.get('.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});
});
