/**
 * @file cypress/tests/data/20-CreateContext.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Dublin Core OAI tests', function() {
	it('Exercise records', function() {
		cy.request('index.php/index/oai?verb=ListRecords&metadataPrefix=oai_dc').then(response => {
			var identifier = null;

			// Ensure we got a valid XML response
			expect(response.status).to.eq(200);
			expect(response.headers['content-type']).to.eq('text/xml;charset=utf-8');

			// Parse the XML response and assert that it's a ListRecords
			const $xml = Cypress.$(Cypress.$.parseXML(response.body)),
				$listRecords = $xml.find('ListRecords');

			// There should only be one ListRecords element
			expect($listRecords.length).to.eq(1);

			// Run some tests on each record
			$listRecords.find('> record').each((index, element) => {
				var $record = Cypress.$(element);
				var $dc = $record.find('> metadata > oai_dc\\:dc');
				expect($dc.length).to.eq(1);

				// Ensure that every element has a title
				expect($dc.find('> dc\\:title').length).to.eq(1);

				// Ensure that every element has at least one author (pkp/pkp-lib#5417)
				expect($dc.find('> dc\\:creator').length).to.be.at.least(1);

				// Save a sample identifier for further exercise
				identifier = $record.find('> header > identifier').text();
			});

			// Make sure we actually tested at least one record
			expect(identifier).to.not.eq(null);

			// Fetch an individual record by identifier
			cy.request('index.php/index/oai?verb=GetRecord&metadataPrefix=oai_dc&identifier=' + encodeURI(identifier)).then(response => {
				// Ensure we got a valid XML response
				expect(response.status).to.eq(200);
				expect(response.headers['content-type']).to.eq('text/xml;charset=utf-8');

				// Parse the XML response and assert that it's a ListRecords
				const $xml = Cypress.$(Cypress.$.parseXML(response.body)),
					$getRecord = $xml.find('GetRecord');

					// There should only be one GetRecord element
					expect($getRecord.length).to.eq(1);
			});
		});
	});
})
