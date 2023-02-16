/**
 * @file cypress/tests/data/60-content/CkwantesSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite: Ckwantes', function() {

	let submission;

	before(function() {
		const title = 'The Facets Of Job Satisfaction: A Nine-Nation Comparative Study Of Construct Equivalence';
		submission = {
			id: 0,
			section: 'Preprints',
			prefix: '',
			title: title,
			subtitle: '',
			abstract: 'Archival data from an attitude survey of employees in a single multinational organization were used to examine the degree to which national culture affects the nature of job satisfaction. Responses from nine countries were compiled to create a benchmark against which nations could be individually compared. Factor analysis revealed four factors: Organizational Communication, Organizational Efficiency/Effectiveness, Organizational Support, and Personal Benefit. Comparisons of factor structures indicated that Organizational Communication exhibited the most construct equivalence, and Personal Benefit the least. The most satisfied employees were those from China, and the least satisfied from Brazil, consistent with previous findings that individuals in collectivistic nations report higher satisfaction. The research findings suggest that national cultural context exerts an effect on the nature of job satisfaction.',
			shortAuthorString: 'Kwantes, et al.',
			authorNames: ['Catherine Kwantes', 'Urho Kekkonen'],
			assignedAuthorNames: ['Catherine Kwantes'],
			authors: [
				{
					givenName: 'Urho',
					familyName: 'Kekkonen',
					email: 'ukk@mailinator.com',
					country: 'Finland',
					affiliation: 'Academy of Finland'
				}
			],
			files: [
				{
					'file': 'dummy.pdf',
					'fileName': title + '.pdf',
					'mimeType': 'application/pdf',
					'genre': Cypress.env('defaultGenre')
				}
			],
		};
	});

	it('Create a submission', function() {
		cy.register({
			'username': 'ckwantes',
			'givenName': 'Catherine',
			'familyName': 'Kwantes',
			'affiliation': 'University of Windsor',
			'country': 'Canada'
		});

		cy.contains('Make a New Submission').click();

		// All required fields in the start submission form
		cy.contains('Begin Submission').click();
		cy.get('#startSubmission-title-error').contains('This field is required.');
		cy.get('#startSubmission-locale-error').contains('This field is required.');
		cy.get('#startSubmission-submissionRequirements-error').contains('This field is required.');
		cy.get('#startSubmission-privacyConsent-error').contains('This field is required.');
		// cy.get('input[name="title"]').type(submission.title, {delay: 0});
		cy.setTinyMceContent('startSubmission-title-control', submission.title);
		cy.get('label:contains("English")').click();
		cy.get('input[name="submissionRequirements"]').check();
		cy.get('input[name="privacyConsent"]').check();
		cy.contains('Begin Submission').click();

		// The submission wizard has loaded
		cy.contains('Make a Submission: Details');
		cy.get('.submissionWizard__submissionDetails').contains('Kwantes');
		cy.get('.submissionWizard__submissionDetails').contains(submission.title);
		cy.contains('Submitting in English');
		cy.get('.pkpSteps__step__label--current').contains('Details');
		cy.get('.pkpSteps__step__label').contains('Upload Files');
		cy.get('.pkpSteps__step__label').contains('Contributors');
		cy.get('.pkpSteps__step__label').contains('For Readers');
		cy.get('.pkpSteps__step__label').contains('Review');

		// Save the submission id for later tests
		cy.location('search')
			.then(search => {
				submission.id = parseInt(search.split('=')[1]);
			});

		// Enter details
		cy.get('h2').contains('Submission Details');
		cy.get('#titleAbstract-keywords-control-en_US').type('employees');
		cy.wait(500);
		cy.get('#titleAbstract-keywords-control-en_US').type('{enter}');
		cy.get('#titleAbstract-keywords-selected-en_US .pkpBadge:contains(\'employees\')');

		cy.get('#titleAbstract-keywords-control-en_US').type('survey');
		cy.wait(500);
		cy.get('#titleAbstract-keywords-control-en_US').type('{enter}');
		cy.get('#titleAbstract-keywords-selected-en_US .pkpBadge:contains(\'survey\')');
		cy.setTinyMceContent('titleAbstract-abstract-control-en_US', submission.abstract);
		cy.get('#titleAbstract-title-control-en_US').click({force: true}); // Ensure blur event is fired

		cy.get('.submissionWizard__footer button').contains('Continue').click();

		// Upload files and set file genres
		cy.contains('Make a Submission: Upload Files');
		cy.get('h2').contains('Upload Files');
		cy.get('h2').contains('Files');
		cy.addSubmissionGalleys(submission.files);

		cy.get('.submissionWizard__footer button').contains('Continue').click();

		// Add Contributors
		cy.contains('Make a Submission: Contributors');
		cy.get('.pkpSteps__step__label--current').contains('Contributors');
		cy.get('h2').contains('Contributors');
		cy.get('.listPanel__item:contains("Catherine Kwantes")');
		cy.get('button').contains('Add Contributor').click();
		cy.get('.modal__panel:contains("Add Contributor")').find('button').contains('Save').click();
		cy.get('#contributor-givenName-error-en_US').contains('This field is required.');
		cy.get('#contributor-email-error').contains('This field is required.');
		cy.get('#contributor-country-error').contains('This field is required.');
		cy.get('.pkpFormField:contains("Given Name")').find('input[name*="en_US"]').type(submission.authors[0].givenName);
		cy.get('.pkpFormField:contains("Family Name")').find('input[name*="en_US"]').type(submission.authors[0].familyName);
		cy.get('.pkpFormField:contains("Country")').find('select').select(submission.authors[0].country)
		cy.get('.pkpFormField:contains("Email")').find('input').type('notanemail');
		cy.get('.modal__panel:contains("Add Contributor")').find('button').contains('Save').click();
		cy.get('#contributor-email-error').contains('This is not a valid email address.');
		cy.get('.pkpFormField:contains("Email")').find('input').type(submission.authors[0].email);
		cy.get('.modal__panel:contains("Add Contributor")').find('button').contains('Save').click();
		cy.get('button').contains('Order').click();
		cy.get('button:contains("Decrease position of Catherine Kwantes")').click();
		cy.get('button').contains('Save Order').click();
		cy.get('button:contains("Preview")').click(); // Will only appear after order is saved
		cy.get('.modal__panel:contains("List of Contributors")').find('tr:contains("Abbreviated")').contains('Kekkonen et al.');
		cy.get('.modal__panel:contains("List of Contributors")').find('tr:contains("Publication Lists")').contains('Urho Kekkonen, Catherine Kwantes (Author)');
		cy.get('.modal__panel:contains("List of Contributors")').find('tr:contains("Full")').contains('Urho Kekkonen, Catherine Kwantes (Author)');
		cy.get('.modal__panel:contains("List of Contributors")').find('.modal__closeButton').click();
		cy.get('.listPanel:contains("Contributors")').find('button').contains('Order').click();
		cy.get('button:contains("Increase position of Catherine Kwantes")').click();
		cy.get('.listPanel:contains("Contributors")').find('button').contains('Save Order').click();
		cy.get('.listPanel:contains("Contributors") button:contains("Preview")').click(); // Will only appear after order is saved
		cy.get('.modal__panel:contains("List of Contributors")').find('tr:contains("Abbreviated")').contains('Kwantes et al.');
		cy.get('.modal__panel:contains("List of Contributors")').find('tr:contains("Publication Lists")').contains('Catherine Kwantes, Urho Kekkonen (Author)');
		cy.get('.modal__panel:contains("List of Contributors")').find('tr:contains("Full")').contains('Catherine Kwantes, Urho Kekkonen (Author)');
		cy.get('.modal__panel:contains("List of Contributors")').find('.modal__closeButton').click();

		// Delete a contributor
		cy.get('.listPanel:contains("Contributors")').find('button').contains('Add Contributor').click();
		cy.get('.pkpFormField:contains("Given Name")').find('input[name*="en_US"]').type('Fake Author Name');
		cy.get('.pkpFormField:contains("Email")').find('input').type('delete@mailinator.com');
		cy.get('.pkpFormField:contains("Country")').find('select').select('Barbados');
		cy.get('.modal__panel:contains("Add Contributor")').find('button').contains('Save').click();
		cy.get('.listPanel__item:contains("Fake Author Name")').find('button').contains('Delete').click();
		cy.get('.modal__panel:contains("Are you sure you want to remove Fake Author Name as a contributor?")').find('button').contains('Delete Contributor').click();
		cy.get('.listPanel__item:contains("Fake Author Name")').should('not.exist');

		cy.get('.submissionWizard__footer button').contains('Continue').click();

		// For Readers
		cy.contains('Make a Submission: For Readers');
		cy.get('.pkpSteps__step__label--current').contains('For Readers');
		cy.get('h2').contains('For Readers');

		cy.get('.submissionWizard__footer button').contains('Continue').click();

		// Review
		cy.contains('Make a Submission: Review');
		cy.get('.pkpSteps__step__label--current').contains('Review');
		cy.get('h2').contains('Review and Submit');
		cy
			.get('h3')
			.contains('Files')
			.parents('.submissionWizard__reviewPanel')
			.contains('PDF')
			.parents('.submissionWizard__reviewPanel')
			.find('.pkpBadge')
			.contains('Preprint Text');

		submission.authorNames.forEach(function(author) {
			cy
				.get('h3')
				.contains('Contributors')
				.parents('.submissionWizard__reviewPanel')
				.contains(author)
				.parents('.submissionWizard__reviewPanel__item__value')
				.find('.pkpBadge')
				.contains('Author');
		});
		cy.get('h3').contains('Details (English)')
			.parents('.submissionWizard__reviewPanel')
			.find('h4').contains('Title').siblings('.submissionWizard__reviewPanel__item__value').contains(submission.title)
			.parents('.submissionWizard__reviewPanel')
			.find('h4').contains('Keywords').siblings('.submissionWizard__reviewPanel__item__value').contains('employees, survey')
			.parents('.submissionWizard__reviewPanel')
			.find('h4').contains('Abstract').siblings('.submissionWizard__reviewPanel__item__value').contains(submission.abstract);
		cy.get('h3').contains('Details (French)')
			.parents('.submissionWizard__reviewPanel')
			.find('h4').contains('Title').siblings('.submissionWizard__reviewPanel__item__value').contains('None provided')
			.parents('.submissionWizard__reviewPanel')
			.find('h4').contains('Keywords').siblings('.submissionWizard__reviewPanel__item__value').contains('None provided')
			.parents('.submissionWizard__reviewPanel')
			.find('h4').contains('Abstract').siblings('.submissionWizard__reviewPanel__item__value').contains('None provided');
		cy.get('h3').contains('For Readers (English)')
		cy.get('h3').contains('For Readers (French)')

		// Save for later
		cy.get('button').contains('Save for Later').click();
		cy.contains('Saved for Later');
		cy.contains('Your submission details have been saved');
		cy.contains('We have emailed a copy of this link to you at ckwantes@mailinator.com.');
		cy.get('a').contains(submission.title).click();

		// Submit
		cy.contains('Make a Submission: Review');
		cy.get('button:contains("Submit")').click();
		const message = 'Are you sure you want to submit ' + submission.title + ' to ' + Cypress.env('contextTitles').en_US + '? Once you submit, a moderator will review the preprint before posting it online.';
		cy.get('.modal__panel:contains("' + message + '")').find('button').contains('Submit').click();
		cy.contains('Submission complete');
		cy.get('a').contains('Create a new submission');
		cy.get('a').contains('Return to your dashboard');
		cy.get('a').contains('Review this submission').click();
		cy.get('h1:contains("' + submission.title + '")');
	});

	it('Publish submission', function() {
		cy.findSubmissionAsEditor('dbarnes', null, 'Kwantes');
		cy.get('.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});
})
