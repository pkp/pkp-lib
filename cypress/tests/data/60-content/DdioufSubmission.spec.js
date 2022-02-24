/**
 * @file cypress/tests/data/60-content/DdioufSubmission.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite tests', function() {
	it('Create a submission', function() {
		var title = 'Genetic transformation of forest trees';
		cy.register({
			'username': 'ddiouf',
			'givenName': 'Diaga',
			'familyName': 'Diouf',
			'affiliation': 'Alexandria University',
			'country': 'Egypt',
		});

		cy.createSubmission({
			title,
			'abstract': 'In this review, the recent progress on genetic transformation of forest trees were discussed. Its described also, different applications of genetic engineering for improving forest trees or understanding the mechanisms governing genes expression in woody plants.',
		});
	});

	it('Declines the submission, reverts the decline, and declines it again', function() {
		cy.findSubmissionAsEditor('dbarnes', null, 'Diouf');
		cy.clickDecision('Decline Submission');
		cy.recordDecisionDecline(['Diaga Diouf']);
		cy.get('.pkp_workflow_last_decision').contains('Submission declined.');
		cy.get('button').contains('Change decision').click();
		cy.clickDecision('Revert Decline');
		cy.recordDecisionRevertDecline(['Diaga Diouf']);
		cy.clickDecision('Decline Submission');
		cy.recordDecisionDecline(['Diaga Diouf']);
	});
});
