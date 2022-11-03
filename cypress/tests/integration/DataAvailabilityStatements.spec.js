/**
 * @file cypress/tests/integration/DataAvailabilityStatements.spec.js
*/

describe('DataAvailabilityStatements', function () {
	var statement = 'This is an example of a data availability statement';

	it('Enables Data Availability Statements as submission metadata', function () {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/management/settings/workflow#submission/metadata');
        cy.contains('Enable Data Availability Statement metadata').find('[type="checkbox"]').check()
		cy.get('#metadata button').contains('Save').click();
		cy.get('#metadata [role="status"]').contains('Saved');
	});
    
    it('Adds a statement to a submission', function () {
        cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/workflow/index/14/1');
        cy.get('#publication-button').click();
        cy.get('#metadata-button').click();
        cy.get('#metadata-dataAvailability-control-en_US').clear();
        cy.get('#metadata-dataAvailability-control-en_US').type(statement);
        cy.get('#metadata button').contains('Save').click();
        cy.get('#metadata [role="status"]').contains('Saved');
    });

    it('Creates a review with disclosed author', function () {
        cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/workflow/index/14/1');
        cy.get('#editorialActions').find('a').contains("Send for Review").click();
        cy.contains('Skip this email').click();
        cy.get('.pkpButton--isPrimary').contains('Record Decision').click();
        cy.visit('index.php/publicknowledge/workflow/access/14');
        cy.contains('Add Reviewer').click();
        cy.contains('Paul Hudson').parentsUntil('.listPanel__item').find('.pkpButton').click();
        cy.get('#skipEmail').check();
        cy.get('#reviewMethod-1').check();
        cy.get("#advancedSearchReviewerForm").contains('Add Reviewer').click();
	});
    
    it('Checks presence in review', function () {
        cy.login('phudson');
        cy.visit('index.php/publicknowledge/reviewer/submission/14');
        cy.contains('View All Submission Details').click();
        cy.contains('Data Availability Statement');
        cy.contains(statement);
	});
});
