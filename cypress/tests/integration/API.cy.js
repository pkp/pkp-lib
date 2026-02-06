/**
 * @file cypress/tests/integration/API.cy.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('API tests', function() {
	it('Sets an API secret', function() {
		// Before API keys will function, an API key secret must be set in the configuration file.
		// This test is used to ensure one is set. (The default configuration file has an empty secret.)
		cy.readFile('config.inc.php').then((text) => {
			cy.writeFile('config.inc.php',
				text.replace("api_key_secret = \"\"", "api_key_secret = \"Api_Key_Secret_For_Testing_Purposes_Only\"")
			);
		});
	});

	it("Tries an API request without authorization", function() {
		cy.request({
			url: 'index.php/publicknowledge/api/v1/users',
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(401);
		});
	});

	it("Configures a manager's API key", function() {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.get('[data-cy="app-user-nav"] button').click();
		cy.get('a:contains("Edit Profile")').click();
		cy.get('a[name="apiSettings"]').click();
		cy.get('#apiProfileForm').then(($apiProfileForm) => {
			if ($apiProfileForm.find("button:contains('Delete')").length > 0) {
				cy.log("Existing API key found, deleting it first");
				cy.get('button').contains("Delete").click();
				cy.waitJQuery();
			} 
    		cy.get('form[id="apiProfileForm"] button:contains("Create API Key")').click();
			cy.waitJQuery();
		});
		cy.get('span:contains("Your changes have been saved.")');
		cy.get('input[id^="apiKey-"]').invoke('val').as('apiKey').then(function() {
			cy.log(this.apiKey);
		});
		cy.logout();
	});

	it('Tries an API request with an API key', function() {
		// Ensure that an API request returns a 200 OK code.
		cy.request('index.php/publicknowledge/api/v1/users?apiToken=' + this.apiKey);
	});

	
});

describe('Peer Reviews API tests', function() {
	let publicationId;
	let submissionId;

	before(function() {
		// Get a publication ID from an existing published article
		cy.login('dbarnes', null, 'publicknowledge');
		cy.request('index.php/publicknowledge/api/v1/submissions?status=3')
			.then(response => {
				expect(response.status).to.eq(200);
				expect(response.body.items).to.have.length.greaterThan(0);
				const submission = response.body.items[0];
				submissionId = submission.id;
				publicationId = submission.publications[0].id;
				cy.log(`Using submission ID: ${submissionId}, publication ID: ${publicationId}`);
			});
	});

	it('Tests peer reviews endpoint without public reviews enabled', function() {
		// Test that the endpoint returns 401 when public reviews are not enabled
		cy.request({
			url: `index.php/publicknowledge/api/v1/peerReviews/open/publications/${publicationId}`,
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(401);
		});
	});

	it('Enables public peer reviews', function() {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.visit('index.php/publicknowledge/management/settings/workflow');
		cy.get('#review-button').click();
		
		// Enable public peer reviews
		cy.get('input[name="enablePublicPeerReviews"]').check();
		cy.get('#review button').contains('Save').click();
		cy.get('span:contains("Saved")');
	});

	it('Gets open peer review for a single publication', function() {
		cy.request({
			url: `index.php/publicknowledge/api/v1/peerReviews/open/publications/${publicationId}`,
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(200);
			expect(response.body).to.have.property('publicationId', publicationId);
			expect(response.body).to.have.property('reviewRounds');
		});
	});

	it('Gets open peer reviews for multiple publications', function() {
		cy.request({
			url: `index.php/publicknowledge/api/v1/peerReviews/open/publications?publicationIds[]=${publicationId}`,
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(200);
			expect(response.body).to.be.an('array');
			if (response.body.length > 0) {
				expect(response.body[0]).to.have.property('publicationId');
				expect(response.body[0]).to.have.property('reviewRounds');
			}
		});
	});

	it('Returns 400 for invalid publication ID in multiple publications request', function() {
		cy.request({
			url: 'index.php/publicknowledge/api/v1/peerReviews/open/publications?publicationIds[]=invalid',
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(400);
			expect(response.body).to.have.property('error');
		});
	});

	it('Returns 404 for non-existent publication', function() {
		cy.request({
			url: 'index.php/publicknowledge/api/v1/peerReviews/open/publications/999999',
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(404);
			expect(response.body).to.have.property('error');
		});
	});

	it('Gets peer review summary for a single publication', function() {
		cy.request({
			url: `index.php/publicknowledge/api/v1/peerReviews/open/publications/${publicationId}/summary`,
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(200);
			expect(response.body).to.have.property('publicationId', publicationId);
			expect(response.body).to.have.property('reviewerRecommendations');
			expect(response.body).to.have.property('reviewerCount');
			expect(response.body).to.have.property('submissionPublishedVersionsCount');
		});
	});

	it('Gets peer review summaries for multiple publications', function() {
		cy.request({
			url: `index.php/publicknowledge/api/v1/peerReviews/open/publications/summary?publicationIds[]=${publicationId}`,
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(200);
			expect(response.body).to.be.an('array');
			if (response.body.length > 0) {
				expect(response.body[0]).to.have.property('publicationId');
				expect(response.body[0]).to.have.property('reviewerRecommendations');
				expect(response.body[0]).to.have.property('reviewerCount');
			}
		});
	});

	it('Returns 400 for invalid publication ID in summaries request', function() {
		cy.request({
			url: 'index.php/publicknowledge/api/v1/peerReviews/open/publications/summary?publicationIds[]=invalid',
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(400);
			expect(response.body).to.have.property('error');
		});
	});

	it('Gets peer review summary for a single submission', function() {
		cy.request({
			url: `index.php/publicknowledge/api/v1/peerReviews/open/submissions/${submissionId}/summary`,
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(200);
			expect(response.body).to.have.property('submissionId', submissionId);
			expect(response.body).to.have.property('reviewerRecommendations');
			expect(response.body).to.have.property('reviewerCount');
			expect(response.body).to.have.property('submissionPublishedVersionsCount');
		});
	});

	it('Returns 404 for non-existent submission', function() {
		cy.request({
			url: 'index.php/publicknowledge/api/v1/peerReviews/open/submissions/999999/summary',
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(404);
			expect(response.body).to.have.property('error');
		});
	});

	it('Gets peer review summaries for multiple submissions', function() {
		cy.request({
			url: `index.php/publicknowledge/api/v1/peerReviews/open/submissions/summary?submissionIds[]=${submissionId}`,
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(200);
			expect(response.body).to.be.an('array');
			if (response.body.length > 0) {
				expect(response.body[0]).to.have.property('submissionId');
				expect(response.body[0]).to.have.property('reviewerRecommendations');
				expect(response.body[0]).to.have.property('reviewerCount');
			}
		});
	});

	it('Returns 400 for invalid submission ID in summaries request', function() {
		cy.request({
			url: 'index.php/publicknowledge/api/v1/peerReviews/open/submissions/summary?submissionIds[]=invalid',
			failOnStatusCode: false
		}).then(response => {
			expect(response.status).to.eq(400);
			expect(response.body).to.have.property('error');
		});
	});

	it('Disables public peer reviews', function() {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.visit('index.php/publicknowledge/management/settings/workflow');
		cy.get('#review-button').click();
		
		// Disable public peer reviews
		cy.get('input[name="enablePublicPeerReviews"]').uncheck();
		cy.get('#review button').contains('Save').click();
		cy.get('span:contains("Saved")');
		cy.logout();
	});

	it('Deletes the manager\'s API key', function() {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.get('[data-cy="app-user-nav"] button').click();
		cy.get('a:contains("Edit Profile")').click();
		cy.get('a[name="apiSettings"]').click();
		cy.get('form[id="apiProfileForm"] button:contains("Delete")').click();
		cy.waitJQuery();
		cy.on('window:confirm', (text) => {
			return true;
		});
		cy.waitJQuery();
		cy.get('span:contains("Your changes have been saved.")');
		cy.get('input[id^="apiKey-"]').invoke('val').should('eq', 'None');
		cy.logout();
	});
})
