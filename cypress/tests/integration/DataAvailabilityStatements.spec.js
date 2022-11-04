/**
 * @file cypress/tests/integration/DataAvailabilityStatements.spec.js
*/

describe('DataAvailabilityStatements', function () {
	var statement = 'This is an example of a data availability statement';
	var author_family_name = 'Daniel';

	it('Enables Data Availability Statements as submission metadata', function () {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/management/settings/workflow#submission/metadata');
		cy.contains('Enable Data Availability Statement metadata').find('[type="checkbox"]').check()
		cy.get('#metadata button').contains('Save').click();
		cy.get('#metadata [role="status"]').contains('Saved');
	});

	it('Adds a statement to a submission', function () {
		cy.findSubmissionAsEditor('dbarnes', null, author_family_name);
		cy.get('#publication-button').click();
		cy.get('#metadata-button').click();
		cy.get('#metadata-dataAvailability-control-en_US').clear();
		cy.get('#metadata-dataAvailability-control-en_US').type(statement);
		cy.get('#metadata button').contains('Save').click();
		cy.get('#metadata [role="status"]').contains('Saved');
	});
});
