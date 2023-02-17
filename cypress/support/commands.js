/**
 * @file cypress/support/commands.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

import Api from '../../lib/pkp/cypress/support/api.js';
import '../../lib/pkp/cypress/support/commands';

Cypress.Commands.add('addCategory', (categoryName, categoryPath) => {
	cy.get('div.pkp_grid_category a[id^=component-grid-settings-category-categorycategorygrid-addCategory-button-]').click();
	cy.wait(1000); // Avoid occasional failure due to form init taking time
	cy.get('input[id^="name-en-"]').type(categoryName, {delay: 0});
	cy.get('input[id^="path-"]').type(categoryPath, {delay: 0});
	cy.get('form[id=categoryForm]').contains('OK').click();
	cy.wait(2000); // Avoid occasional failure due to form save taking time
});

Cypress.Commands.add('addSubmissionGalleys', (files) => {
	files.forEach(file => {
		cy.get('a:contains("Add File")').click();
		cy.wait(2000); // Avoid occasional failure due to form init taking time
		cy.get('div.pkp_modal_panel').then($modalDiv => {
			cy.wait(3000);
			$modalDiv.find('div.header:contains("Add File")');
			cy.get('div.pkp_modal_panel input[id^="label-"]').type('PDF', {delay: 0});
			cy.get('div.pkp_modal_panel button:contains("Save")').click();
			cy.wait(2000); // Avoid occasional failure due to form init taking time
		});
		cy.get('select[id=genreId]').select(file.genre);
		cy.fixture(file.file, 'base64').then(fileContent => {
			cy.get('input[type=file]').attachFile(
				{fileContent, 'filePath': file.fileName, 'mimeType': 'application/pdf', 'encoding': 'base64'}
			);
		});
		cy.get('#continueButton').click();
		cy.wait(2000);
		for (const field in file.metadata) {
			cy.get('input[id^="' + Cypress.$.escapeSelector(field) + '"]:visible,textarea[id^="' + Cypress.$.escapeSelector(field) + '"]').type(file.metadata[field], {delay: 0});
			cy.get('input[id^="language"').click({force: true}); // Close multilingual and datepicker pop-overs
		}
		cy.get('#continueButton').click();
		cy.get('#continueButton').click();
	});
});

Cypress.Commands.add('createSubmissionWithApi', (data, csrfToken) => {
	const api = new Api(Cypress.env('baseUrl') + '/index.php/publicknowledge/api/v1');

	return cy.beginSubmissionWithApi(api, data, csrfToken)
		.putMetadataWithApi(data, csrfToken)
		.get('@submissionId').then((submissionId) => {
			if (typeof data.files === 'undefined' || !data.files.length) {
				return;
			}
			cy.visit('/index.php/publicknowledge/submission?id=' + submissionId);
			cy.get('button:contains("Continue")').click();

			// Must use the UI to upload files until we upgrade Cypress
			// to 7.4.0 or higher.
			// @see https://github.com/cypress-io/cypress/issues/1647
			cy.addSubmissionGalleys(data.files);
		})
		.addSubmissionAuthorsWithApi(api, data, csrfToken);
});

