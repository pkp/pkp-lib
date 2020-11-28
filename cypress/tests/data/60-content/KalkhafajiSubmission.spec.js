/**
 * @file cypress/tests/data/60-content/KalkhafajiSubmission.spec.js
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite tests', function() {
	it('Create a submission', function() {
		var title = 'Learning Sustainable Design through Service';
		cy.register({
			'username': 'kalkhafaji',
			'givenName': 'Karim',
			'familyName': 'Al-Khafaji',
			'affiliation': 'Stanford University',
			'country': 'United States',
		});

		cy.createSubmission({
			title,
			'abstract': 'Environmental sustainability and sustainable development principles are vital topics that engineering education has largely failed to address. Service-learning, which integrates social service into an academic setting, is an emerging tool that can be leveraged to teach sustainable design to future engineers. We present a model of using service-learning to teach sustainable design based on the experiences of the Stanford chapter of Engineers for a Sustainable World. The model involves the identification of projects and partner organizations, a student led, project-based design course, and internships coordinated with partner organizations. The model has been very successful, although limitations and challenges exist. These are discussed along with future directions for expanding the model.',
			'keywords': [
				'Development',
				'engineering education',
				'service learning',
				'sustainability',
			],
			'additionalAuthors': [
				{
					'givenName': 'Margaret',
					'familyName': 'Morse',
					'country': 'United States',
					'affiliation': 'Stanford University',
					'email': 'mmorse@mailinator.com',
				}
			],
		});

		cy.logout();
		cy.findSubmissionAsEditor('dbarnes', null, 'Al-Khafaji');
		cy.get('ul.pkp_workflow_decisions button:contains("Post the preprint")').click();
		cy.get('div.pkpPublication button:contains("Post"):visible').click();
		cy.get('div:contains("All requirements have been met. Are you sure you want to post this?")');
		cy.get('[id^="publish"] button:contains("Post")').click();
	});
});
