/**
 * @file cypress/tests/integration/DataAvailabilityStatements.spec.js
*/

describe('DataAvailabilityStatements', function () {
	var statement = 'This is an example of a data availability statement';
	var submission = {
		title: 'Towards Designing an Intercultural Curriculum',
		author_family_name: 'Daniel'
	}
	var reviewer = { name: 'Paul Hudson', login: 'phudson' }

	it('Enables Data Availability Statements as submission metadata', function () {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/management/settings/workflow#submission/metadata');
		cy.contains('Enable Data Availability Statement metadata').find('[type="checkbox"]').check()
		cy.get('#metadata button').contains('Save').click();
		cy.get('#metadata [role="status"]').contains('Saved');
	});

	it('Adds a statement to a submission', function () {
		cy.findSubmissionAsEditor('dbarnes', null, submission.author_family_name);
		cy.get('#publication-button').click();
		cy.get('#metadata-button').click();
		cy.get('#metadata-dataAvailability-control-en_US').clear();
		cy.get('#metadata-dataAvailability-control-en_US').type(statement);
		cy.get('#metadata button').contains('Save').click();
		cy.get('#metadata [role="status"]').contains('Saved');
	});

	it('Sends submission for review with "disclosed author" method and check Statement as reviewer', function () {
		cy.findSubmissionAsEditor('dbarnes', null, submission.author_family_name);
		cy.clickDecision('Send for Review');
		cy.contains('Skip this email').click();
		cy.get('.pkpButton--isPrimary').contains('Record Decision').click();
		cy.findSubmissionAsEditor('dbarnes', null, submission.author_family_name);
		cy.contains('Add Reviewer').click();
		cy.contains(reviewer.name).parentsUntil('.listPanel__item').find('.pkpButton').click();
		cy.get('#skipEmail').check();
		cy.get('#reviewMethod-1').check();
		cy.get('#advancedSearchReviewerForm').contains('Add Reviewer').click();
		cy.logout();
		cy.login(reviewer.login);
		cy.contains('View ' + submission.title).click();
		cy.contains('View All Submission Details').click();
		cy.contains('Data Availability Statement');
		cy.contains(statement);
	});
});
