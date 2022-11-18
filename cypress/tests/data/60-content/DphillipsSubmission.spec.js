/**
 * @file cypress/tests/data/60-content/DphillipsSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite: Dphillips', function() {
	let submission;

	before(function() {
		const title = 'Investigating the Shared Background Required for Argument: A Critique of Fogelin\'s Thesis on Deep Disagreement';
		submission = {
			id: 0,
			section: 'Preprints',
			prefix: '',
			title: title,
			subtitle: '',
			abstract: 'Robert Fogelin claims that interlocutors must share a framework of background beliefs and commitments in order to fruitfully pursue argument. I refute Fogelin’s claim by investigating more thoroughly the shared background required for productive argument. I find that this background consists not in any common beliefs regarding the topic at hand, but rather in certain shared pro-cedural commitments and competencies. I suggest that Fogelin and his supporters mistakenly view shared beliefs as part of the required background for productive argument because these procedural com-mitments become more difficult to uphold when people’s beliefs diverge widely regarding the topic at hand.',
			shortAuthorString: 'Phillips',
			authorNames: ['Dana Phillips'],
			sectionId: 1,
			assignedAuthorNames: ['Dana Phillips'],
			authors: [
				{
					givenName: 'Dana',
					familyName: 'Phillips',
					email: 'ddiouf@mailinator.com',
					country: 'Canada',
					affiliation: 'University of Toronto'
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
			'username': 'dphillips',
			'givenName': 'Dana',
			'familyName': 'Phillips',
			'affiliation': 'University of Toronto',
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
		cy.findSubmissionAsEditor('dbarnes', null, 'Phillips');
		cy.get('.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});

	it('Preprint is not available when unposted', function() {
		cy.login('dbarnes');
		cy.visit('/index.php/publicknowledge/workflow/access/' + submission.id);
		cy.get('#publication-button').click();
		cy.get('button').contains('Unpost').click();
		cy.contains('Are you sure you don\'t want this to be posted?');
		cy.get('.modal__panel button').contains('Unpost').click();
		cy.wait(1000);
		cy.visit('/index.php/publicknowledge/preprints');
		cy.contains('Signalling Theory Dividends').should('not.exist');
		cy.logout();
		cy.request({
				url: '/index.php/publicknowledge/preprint/view/' + submission.id,
				failOnStatusCode: false
			})
			.then((response) => {
				expect(response.status).to.equal(404);
			});

		// Re-post it
		cy.login('dbarnes');
		cy.visit('/index.php/publicknowledge/workflow/access/' + submission.id);
		cy.get('#publication-button').click();
		cy.get('.pkpPublication button').contains('Post').click();
		cy.contains('All requirements have been met.');
		cy.get('.pkpWorkflow__publishModal button').contains('Post').click();
	});
});
