/**
 * @file cypress/tests/data/60-content/JmwandengaSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite: Jmwandenga', function() {
	let submission;

	before(function() {
		const title = 'Signalling Theory Dividends: A Review Of The Literature And Empirical Evidence';
		submission = {
			id: 0,
			section: 'Preprints',
			prefix: '',
			title: title,
			subtitle: '',
			abstract: 'The signaling theory suggests that dividends signal future prospects of a firm. However, recent empirical evidence from the US and the Uk does not offer a conclusive evidence on this issue. There are conflicting policy implications among financial economists so much that there is no practical dividend policy guidance to management, existing and potential investors in shareholding. Since corporate investment, financing and distribution decisions are a continuous function of management, the dividend decisions seem to rely on intuitive evaluation.',
			shortAuthorString: 'Mwandenga',
			authorNames: ['John Mwandenga'],
			sectionId: 1,
			assignedAuthorNames: ['John Mwandenga'],
			authors: [
				{
					givenName: 'John',
					familyName: 'Mwandenga',
					email: 'jmwandenga@mailinator.com',
					country: 'University of Cape Town',
					affiliation: 'South Africa'
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
			'username': 'jmwandenga',
			'givenName': 'John',
			'familyName': 'Mwandenga',
			'affiliation': 'University of Cape Town',
			'country': 'South Africa',
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
		cy.findSubmissionAsEditor('dbarnes', null, 'Mwandenga');
		cy.get('.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});
});
