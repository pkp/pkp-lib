/**
 * @file cypress/tests/integration/NativeXmlImportExportSubmission.cy.js
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite tests', function() {
	const downloadedSubmissionPath = Cypress.config('downloadsFolder') + "/native-submission.xml";
	it('Exports submissions to XML', function() {
		var username = 'admin';
		cy.login(username, 'admin');

		cy.get('li.profile a:contains("' + username + '")').click();
		cy.get('li.profile a:contains("Dashboard")').click();
		cy.get('.app__nav a').contains('Tools').click();
		cy.get('a:contains("Native XML Plugin")').click();
		cy.get('a[href="#exportSubmissions-tab"]').click();
		cy.waitJQuery();
		// Export first 2 submissions
		cy.get('input[name="selectedSubmissions[]"]:lt(2)').check();

		cy.get('form#exportXmlForm button[type="submit"]').click({timeout:60000});

		cy.contains('The export completed successfully.', {timeout:60000});
		cy.intercept({method: 'POST'}, (req) => {
			req.redirect('/');
		}).as('download');
		cy.contains('Download Exported File').parents('form').first().submit();
		cy.wait('@download').its('request').then((req) => {
			cy.request(req).then((res) => {
				expect(res).to.have.property('status', 200);
				expect(res.headers).to.have.property('content-type', 'text/xml;charset=utf-8');
				cy.writeFile(downloadedSubmissionPath, res.body, 'utf8');
			});
		});
	});
	it.only('Imports submissions from XML', function() {
		var username = 'admin';
		cy.login(username, 'admin');

		cy.get('li.profile a:contains("' + username + '")').click();
		cy.get('li.profile a:contains("Dashboard")').click();
		cy.get('.app__nav a').contains('Tools').click();
		// The a:contains(...) syntax ensures that it will wait for the
		// tab to load. Do not convert to cy.get('a').contains('Native XML Plugin')
		cy.get('a:contains("Native XML Plugin")').click();

		cy.wait(250);
		cy.readFile(downloadedSubmissionPath).then(fileContent => {
			cy.get('input[type=file]').attachFile({fileContent, filePath: downloadedSubmissionPath, mimeType: 'text/xml', encoding: 'utf8'});
		});

		cy.get('input[name="temporaryFileId"][value!=""]', {timeout:20000});

		cy.get('form#importXmlForm button[type="submit"]').click();

		cy.contains('The import completed successfully.', {timeout:20000});
	});
});
