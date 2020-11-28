/**
 * @file cypress/tests/data/60-content/EostromSubmission.spec.js
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite tests', function() {
	it('Create a submission', function() {
		var title = 'Traditions and Trends in the Study of the Commons';
		cy.register({
			'username': 'eostrom',
			'givenName': 'Elinor',
			'familyName': 'Ostrom',
			'affiliation': 'Indiana University',
			'country': 'United States',
		});

		cy.createSubmission({
			title,
			'abstract': 'The study of the commons has expe- rienced substantial growth and development over the past decades.1 Distinguished scholars in many disciplines had long studied how specific resources were managed or mismanaged at particular times and places (Coward 1980; De los Reyes 1980; MacKenzie 1979; Wittfogel 1957), but researchers who studied specific commons before the mid-1980s were, however, less likely than their contemporary colleagues to be well informed about the work of scholars in other disciplines, about other sec- tors in their own region of interest, or in other regions of the world. ',
			'keywords': [
				'Common pool resource',
				'common property',
				'intellectual developments'
			],
			'additionalAuthors': [
				{
					'givenName': 'Frank',
					'familyName': 'van Laerhoven',
					'country': 'United States',
					'affiliation': 'Indiana University',
					'email': 'fvanlaerhoven@mailinator.com'
				}
			]
		});

		cy.logout();
		cy.findSubmissionAsEditor('dbarnes', null, 'Ostrom');
		cy.get('ul.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});
});
