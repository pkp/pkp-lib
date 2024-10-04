/**
 * @file cypress/tests/integration/DataAvailabilityStatements.cy.js
*/

// Temporarly Skip until OMP&OPS is migrated to new side modal workflow

describe.skip('DataAvailabilityStatements', function () {
	var config = Cypress.env('dataAvailabilityTest');
	var statement = 'This is an example of a data availability statement';

	it('Enables Data Availability Statements as submission metadata', function () {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/management/settings/workflow');
		cy.get('button').contains('Metadata').click();
		cy.get('label:contains("Enable data availability statement metadata")').click();
		cy.get('#metadata button').contains('Save').click();
		cy.get('#metadata [role="status"]').contains('Saved');
	});

	it('Adds a statement to a submission', function () {
		cy.findSubmissionAsEditor('dbarnes', null, config.submission.authorFamilyName);
		cy.openWorkflowMenu('Metadata')
		cy.setTinyMceContent('metadata-dataAvailability-control-en', statement);
		cy.get('button').contains('Save').click();
		cy.get('[role="status"]').contains('Saved');
	});

	if (config.anonymousReviewer) {
		it('Checks that anonymous reviewers can not view data availability statement', function() {
			cy.login(config.anonymousReviewer);
			cy.visit("/index.php/publicknowledge/dashboard/reviewAssignments");
			cy.openReviewAssignment(config.submission.title)
			cy.contains('View All Submission Details').click();
			cy.contains('Data Availability Statement').should('not.exist');
			cy.contains(statement).should('not.exist');
		});
	}

	if (config.anonymousDisclosedReviewer) {
		it('Checks that reviewers with the author disclosed can view data availability statement', function() {
			cy.login(config.anonymousDisclosedReviewer);
			cy.visit("/index.php/publicknowledge/dashboard/reviewAssignments");
			cy.openReviewAssignment(config.submission.title)
			cy.contains('View All Submission Details').click();
			cy.contains('Data Availability Statement');
			cy.contains(statement);
		});
	}
});
