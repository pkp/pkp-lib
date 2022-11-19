/**
 * @file cypress/tests/data/60-content/EostromSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite: Eostrom', function() {
	let submission;

	before(function() {
		const title = 'Developing efficacy beliefs in the classroom';
		submission = {
			id: 0,
			section: 'Preprints',
			prefix: '',
			title: title,
			subtitle: '',
			abstract: 'The study of the commons has expe- rienced substantial growth and development over the past decades.1 Distinguished scholars in many disciplines had long studied how specific resources were managed or mismanaged at particular times and places (Coward 1980; De los Reyes 1980; MacKenzie 1979; Wittfogel 1957), but researchers who studied specific commons before the mid-1980s were, however, less likely than their contemporary colleagues to be well informed about the work of scholars in other disciplines, about other sec- tors in their own region of interest, or in other regions of the world.',
			shortAuthorString: 'Ostrom',
			authorNames: ['Elinor Ostrom'],
			sectionId: 1,
			assignedAuthorNames: ['Elinor Ostrom'],
			additionalAuthors: [
				{
					givenName: {en_US: 'Frank'},
					familyName: {en_US: 'van Laerhoven'},
					country: 'US',
					affiliation: {en_US: 'Indiana University'},
					email: 'fvanlaerhoven@mailinator.com',
					userGroupId: Cypress.env('authorUserGroupId')
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
				'Common pool resource',
				'common property',
				'intellectual developments'
			]
		};
	});

	it('Create a submission', function() {
		var title = 'Traditions and Trends in the Study of the Commons';
		cy.register({
			'username': 'eostrom',
			'givenName': 'Elinor',
			'familyName': 'Ostrom',
			'affiliation': 'Indiana University',
			'country': 'United States',
		});

		cy.getCsrfToken();
		cy.window()
			.then(() => {
				return cy.createSubmissionWithApi(submission, this.csrfToken);
			})
			.then(xhr => {
				return cy.submitSubmissionWithApi(submission.id, this.csrfToken);
			});

		cy.logout();
		cy.findSubmissionAsEditor('dbarnes', null, 'Ostrom');
		cy.get('.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});
});
