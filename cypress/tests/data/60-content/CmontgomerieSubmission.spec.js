/**
 * @file cypress/tests/data/60-content/CmontgomerieSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite: Cmontgomerie', function() {

	let submission;

	before(function() {
		const title = 'Computer Skill Requirements for New and Existing Teachers: Implications for Policy and Practice';
		submission = {
			id: 0,
			section: 'Preprints',
			prefix: '',
			title: title,
			subtitle: '',
			abstract: 'The integration of technology into the classroom is a major issue in education today. Many national and provincial initiatives specify the technology skills that students must demonstrate at each grade level. The Government of the Province of Alberta in Canada, has mandated the implementation of a new curriculum which began in September of 2000, called Information and Communication Technology. This curriculum is infused within core courses and specifies what students are “expected to know, be able to do, and be like with respect to technology” (Alberta Learning, 2000). Since teachers are required to implement this new curriculum, school jurisdictions are turning to professional development strategies and hiring standards to upgrade teachers’ computer skills to meet this goal. This paper summarizes the results of a telephone survey administered to all public school jurisdictions in the Province of Alberta with a 100% response rate. We examined the computer skills that school jurisdictions require of newly hired teachers, and the support strategies employed for currently employed teachers.',
			shortAuthorString: 'Montgomerie et al.',
			authorNames: ['Craig Montgomerie', 'Mark Irvine'],
			sectionId: 1,
			assignedAuthorNames: ['Craig Montgomerie'],
			authors: [
				{
					givenName: 'Craig',
					familyName: 'Montgomerie',
					email: 'cmontgomerie@mailinator.com',
					country: 'Canada',
					affiliation: 'University of Alberta'
				},
				{
					givenName: 'Mark',
					familyName: 'Irvine',
					country: 'Canada',
					affiliation: 'University of Victoria',
					email: 'mirvine@mailinator.com'
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
				'Integrating Technology',
				'Computer Skills',
				'Survey',
				'Alberta',
				'National',
				'Provincial',
				'Professional Development'
			]
		};
	});

	it('Create a submission', function() {

		cy.register({
			'username': 'cmontgomerie',
			'givenName': 'Craig',
			'familyName': 'Montgomerie',
			'affiliation': 'University of Alberta',
			'country': 'Canada'
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
		cy.findSubmissionAsEditor('dbarnes', null, 'Montgomerie');
		cy.get('.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
		cy.logout();

		// Unpost 1st version
		cy.findSubmissionAsEditor('dbarnes', null, 'Montgomerie');
		cy.get('#publication-button').click();
		cy.get('div.pkpPublication button:contains("Unpost"):visible').click();
		cy.get('div:contains("Are you sure you don\'t want this to be posted?")');
		cy.get('.modal__footer button').contains('Unpost').click();

		// Edit metadata in 1st version
		cy.get('#metadata-button').click();
		cy.get('#metadata-keywords-control-en_US').type('employees{enter}', {delay: 0});
		cy.wait(500);
		cy.get('#metadata-keywords-control-en_US').type('{enter}', {delay: 0});
		cy.get('#metadata button').contains('Save').click();
		cy.get('#metadata [role="status"]').contains('Saved');
		cy.get('#metadata-keywords-selected-en_US').contains('employees');
		cy.wait(1500);

		// Publish 1st version again
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();

		// Create 2nd version and change copyright holder
		cy.get('div.pkpPublication button:contains("Create New Version"):visible').click();
		cy.get('div:contains("Are you sure you want to create a new version?")');
		cy.get('.modal__footer button').contains('Yes').click();
		cy.get('#license-button').click();
		cy.get('input[id^="publicationLicense-copyrightHolder-control-en_US"').clear()
		cy.get('input[id^="publicationLicense-copyrightHolder-control-en_US"').type('Craig Montgomerie', {delay: 0});
		cy.get('#license button').contains('Save').click();
		cy.get('#license [role="status"]').contains('Saved');
		cy.wait(1500);

		// Publish 2nd version
		cy.get('#publication button').contains('Post').click();
		cy.contains('All requirements have been met.');
		cy.get('.pkpWorkflow__publishModal button').contains('Post').click();
	});
})
