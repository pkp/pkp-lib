/**
 * @file cypress/tests/data/60-content/VwilliamsonSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite: Vwilliamson', function() {
	let submission;

	before(function() {
		const title = 'Self-Organization in Multi-Level Institutions in Networked Environments';
		submission = {
			id: 0,
			section: 'Preprints',
			prefix: '',
			title: title,
			subtitle: '',
			abstract: 'We compare a setting where actors individually decide whom to sanction with a setting where sanctions are only implemented when actors collectively agree that a certain actor should be sanctioned. Collective sanctioning decisions are problematic due to the difficulty of reaching consensus. However, when a decision is made collectively, perverse sanctioning (e.g. punishing high contributors) by individual actors is ruled out. Therefore, collective sanctioning decisions are likely to be in the interest of the whole group.',
			shortAuthorString: 'Williamson',
			authorNames: ['Valerie Williamson'],
			sectionId: 1,
			assignedAuthorNames: ['Valerie Williamson'],
			authors: [
				{
					givenName: 'Valerie',
					familyName: 'Williamson',
					email: 'vwilliamson@mailinator.com',
					country: 'Canada',
					affiliation: 'University of Windsor'
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
				'Self-Organization',
				'Multi-Level Institutions',
				'Goverance',
			]
		};
	});

	it('Create a submission', function() {
		cy.register({
			'username': 'vwilliamson',
			'givenName': 'Valerie',
			'familyName': 'Williamson',
			'affiliation': 'University of Windsor',
			'country': 'Canada',
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
		cy.findSubmissionAsEditor('dbarnes', null, 'Williamson');
		cy.get('.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});
});
