/**
 * @file cypress/tests/integration/Doi.spec.js
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('DOI tests', function() {
	const submissionId = 19;
	const publicationId = 20;
	const galleyId = 20;

	function checkDoiInput(input) {
		const val = input.val();
		expect(val).to.match(/10.1234\/[0-9abcdefghjkmnpqrstvwxyz]{4}-[0-9abcdefghjkmnpqrstvwxyz]{2}[0-9]{2}/);
	}

	it('Check DOI Configuration', function() {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.get('a:contains("Distribution")').click();

		cy.get('button#dois-button').click();

		// DOI is or can be enabled
		cy.get('input[name="enableDois"]').check();
		cy.get('input[name="enableDois"]').should('be.checked');

		// Check all content
		cy.get('input[name="enabledDoiTypes"][value="publication"]').check();
		cy.get('input[name="enabledDoiTypes"][value="representation"]').check();

		// Declare DOI Prefix
		cy.get('input[name=doiPrefix]').focus().clear().type('10.1234');

		// Save
		cy.get('#doisSetup button').contains('Save').click();
		cy.get('#doisSetup [role="status"]').contains('Saved');
	});

	it('Check Publication/Galley DOI Assignments', function() {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.get('a:contains("DOIs")').click();
		cy.get('button#preprint-doi-management-button').click();

		// Select the first article
		cy.get(`input[name="submission[]"][value=${submissionId}]`).check()

		// Select assign DOIs from bulk actions
		cy.get('#preprint-doi-management button:contains("Bulk Actions")').click({multiple: true});
		cy.get('button#openBulkAssign').click();

		// Confirm assignment
		cy.get('div[data-modal="bulkActions"] button:contains("Assign DOIs")').click();
		cy.get('.app__notifications').contains('Items successfully assigned new DOIs', {timeout:20000});

		cy.get(`#list-item-submission-${submissionId} button.expander`).click();
		cy.get(`input#${submissionId}-preprint-${publicationId}`)
			.should(($input) => checkDoiInput($input));
		cy.get(`input#${submissionId}-galley-${galleyId}`)
			.should(($input) => checkDoiInput($input));
	});

	it('Check Publication/Galley DOI visible', function() {
		cy.login('dbarnes', null, 'publicknowledge');

		// Select a submission
		cy.visit(`/index.php/publicknowledge/preprint/view/${submissionId}`);

		cy.get('section.item.doi')
			.find('span.value').contains('https://doi.org/10.1234/');
	});

	it('Check Publication/Galley Marked Registered', function() {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.get('a:contains("DOIs")').click();
		cy.get('button#preprint-doi-management-button').click();

		// Select the first preprint
		cy.get(`input[name="submission[]"][value=${submissionId}]`).check()

		// Select mark registered from bulk actions
		cy.get('#preprint-doi-management button:contains("Bulk Actions")').click({multiple: true});
		cy.get('button#openBulkMarkRegistered').click();

		// Confirm assignment
		cy.get('div[data-modal="bulkActions"] button:contains("Mark DOIs registered")').click();
		cy.get('.app__notifications').contains('Items successfully marked registered', {timeout:20000});

		cy.get(`#list-item-submission-${submissionId} .pkpBadge`).contains('Registered');
	});
});
