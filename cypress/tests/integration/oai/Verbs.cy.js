/**
 * @file cypress/tests/integration/oai/Verbs.cy.js
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Smoke test that each OAI-PMH verb endpoint returns HTTP 200 and a
 * well-formed XML response containing the expected verb element and no error.
 */

describe('OAI-PMH verb endpoints', function() {
	// ListRecords and ListIdentifiers require a metadataPrefix; the rest do not.
	const verbs = [
		{verb: 'Identify', params: ''},
		{verb: 'ListMetadataFormats', params: ''},
		{verb: 'ListSets', params: ''},
		{verb: 'ListIdentifiers', params: '&metadataPrefix=oai_dc'},
		{verb: 'ListRecords', params: '&metadataPrefix=oai_dc'},
	];

	verbs.forEach(({verb, params}) => {
		it(verb + ' returns 200 and XML', function() {
			cy.request('index.php/index/oai?verb=' + verb + params).then(response => {
				// HTTP 200 with an XML content type
				expect(response.status).to.eq(200);
				expect(response.headers['content-type']).to.eq('text/xml;charset=utf-8');

				// Well-formed OAI-PMH document
				const $xml = Cypress.$(Cypress.$.parseXML(response.body));
				expect($xml.find('OAI-PMH').length).to.eq(1);

				// The response carries the requested verb element...
				expect($xml.find(verb).length).to.eq(1);

				// ...and did not return a protocol error
				const $error = $xml.find('error');
				expect($error.length, $error.text()).to.eq(0);
			});
		});
	});
});
