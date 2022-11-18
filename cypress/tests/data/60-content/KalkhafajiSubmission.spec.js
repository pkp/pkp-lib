/**
 * @file cypress/tests/data/60-content/KalkhafajiSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite: Kalkhafaji', function() {
	let submission;

	before(function() {
		const title = 'Learning Sustainable Design through Service';
		submission = {
			id: 0,
			section: 'Preprints',
			prefix: '',
			title: title,
			subtitle: '',
			abstract: 'Environmental sustainability and sustainable development principles are vital topics that engineering education has largely failed to address. Service-learning, which integrates social service into an academic setting, is an emerging tool that can be leveraged to teach sustainable design to future engineers. We present a model of using service-learning to teach sustainable design based on the experiences of the Stanford chapter of Engineers for a Sustainable World. The model involves the identification of projects and partner organizations, a student led, project-based design course, and internships coordinated with partner organizations. The model has been very successful, although limitations and challenges exist. These are discussed along with future directions for expanding the model.',
			shortAuthorString: 'Al-Khafaji',
			authorNames: ['Karim Al-Khafaji'],
			sectionId: 1,
			assignedAuthorNames: ['Karim Al-Khafaji'],
			authors: [
				{
					givenName: 'Karim',
					familyName: 'Al-Khafaji',
					email: 'kalkhafaji@mailinator.com',
					country: 'United States',
					affiliation: 'Stanford University'
				},
				{
					givenName: 'Margaret',
					familyName: 'Morse',
					country: 'United States',
					affiliation: 'Stanford University',
					email: 'mmorse@mailinator.com',
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
				'Development',
				'engineering education',
				'service learning',
				'sustainability',
			]
		};
	});

	it('Create a submission', function() {
		cy.register({
			'username': 'kalkhafaji',
			'givenName': 'Karim',
			'familyName': 'Al-Khafaji',
			'affiliation': 'Stanford University',
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
		cy.findSubmissionAsEditor('dbarnes', null, 'Al-Khafaji');
		cy.get('.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});
});
