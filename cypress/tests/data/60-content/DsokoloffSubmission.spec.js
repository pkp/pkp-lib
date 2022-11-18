/**
 * @file cypress/tests/data/60-content/DsokoloffSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite: Dsokoloff', function() {
	let submission;

	before(function() {
		const title = 'Developing efficacy beliefs in the classroom';
		submission = {
			id: 0,
			section: 'Preprints',
			prefix: '',
			title: title,
			subtitle: '',
			abstract: 'A major goal of education is to equip children with the knowledge, skills and self-belief to be confident and informed citizens - citizens who continue to see themselves as learners beyond graduation. This paper looks at the key role of nurturing efficacy beliefs in order to learn and participate in school and society. Research findings conducted within a social studies context are presented, showing how strategy instruction can enhance self-efficacy for learning. As part of this research, Creative Problem Solving (CPS) was taught to children as a means to motivate and support learning. It is shown that the use of CPS can have positive effects on self-efficacy for learning, and be a valuable framework to involve children in decision-making that leads to social action. Implications for enhancing self-efficacy and motivation to learn in the classroom are discussed.',
			shortAuthorString: 'Sokoloff',
			authorNames: ['Domatilia Sokoloff'],
			sectionId: 1,
			assignedAuthorNames: ['Domatilia Sokoloff'],
			authors: [
				{
					givenName: 'Domatilia',
					familyName: 'Sokoloff',
					email: 'dsokoloff@mailinator.com',
					country: 'Ireland',
					affiliation: 'University College Cork'
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
		var title = 'Developing efficacy beliefs in the classroom';
		cy.register({
			'username': 'dsokoloff',
			'givenName': 'Domatilia',
			'familyName': 'Sokoloff',
			'affiliation': 'University College Cork',
			'country': 'Ireland',
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
		cy.findSubmissionAsEditor('dbarnes', null, 'Sokoloff');
		cy.get('.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});
});
