/**
 * @file cypress/tests/integration/DataAvailabilityStatements.spec.js
*/

describe('DataAvailabilityStatements', function () {
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
		cy.get('#publication-button').click();
		cy.get('#metadata-button').click();
		cy.setTinyMceContent('metadata-dataAvailability-control-en_US', statement);
		cy.get('#metadata button').contains('Save').click();
		cy.get('#metadata [role="status"]').contains('Saved');
	});

	if (config.anonymousReviewer) {
		it('Checks that anonymous reviewers can not view data availability statement', function() {
			cy.login(config.anonymousReviewer);
			cy.visit("/index.php/publicknowledge/submissions");
			cy.contains('View ' + config.submission.title).click();
			cy.contains('View All Submission Details').click();
			cy.contains('Data Availability Statement').should('not.exist');
			cy.contains(statement).should('not.exist');
		});
	}

	if (config.anonymousDisclosedReviewer) {
		it('Checks that reviewers with the author disclosed can view data availability statement', function() {
			cy.login(config.anonymousDisclosedReviewer);
			cy.visit("/index.php/publicknowledge/submissions");
			cy.contains('View ' + config.submission.title).click();
			cy.contains('View All Submission Details').click();
			cy.contains('Data Availability Statement');
			cy.contains(statement);
		});
	}
});
