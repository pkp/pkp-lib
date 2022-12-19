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
	const unpublishedSubmissionId = 1;

	const loginAndGoToDoiPage = () => {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.get('a:contains("DOIs")').click();
		cy.get('button#submission-doi-management-button').click();
	};

	const clearFilter = () => {
		cy.get('#submission-doi-management button:contains("Clear filter")').click();
	};

	it('Check DOI Configuration', function() {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.checkDoiConfig(['publication', 'representation']);
	});

	it('Check DOI Assignments and Visibility', function() {
		cy.log('Check Submission Assignment');
		loginAndGoToDoiPage();
		cy.assignDois(submissionId);

		cy.get(`#list-item-submission-${submissionId} button.expander`).click();
		cy.checkDoiAssignment(`${submissionId}-preprint-${publicationId}`);
		cy.checkDoiAssignment(`${submissionId}-galley-${galleyId}`);

		cy.log('Check Submission Visibility');
		// Select a submission
		cy.visit(`/index.php/publicknowledge/preprint/view/${submissionId}`);

		cy.get('section.item.doi')
			.find('span.value').contains('https://doi.org/10.1234/');
	});

	it('Check filters and mark registered', function() {
		cy.log('Check Submission Filter Behaviour (pre-deposit)');
		loginAndGoToDoiPage();

		cy.checkDoiFilterResults('Needs DOI', 'Williamson — Self-Organization in Multi-Level Institutions in Networked Environments', 18);
		cy.checkDoiFilterResults('Unpublished', 'No items found.', 0);
		cy.checkDoiFilterResults('Unregistered', 'Woods — Finocchiaro: Arguments About Arguments', 1);
		clearFilter();

		cy.log('Check Submission Marked Registered');
		cy.checkDoiMarkedStatus('Registered', submissionId, true, 'Registered');

		cy.log('Check Submission Filter Behaviour (post-deposit)');
		cy.checkDoiFilterResults('Submitted', 'No items found.', 0);
		cy.checkDoiFilterResults('Registered', 'Woods — Finocchiaro: Arguments About Arguments', 1);

	});

	it('Check Marked Status Behaviour', function() {
		loginAndGoToDoiPage();

		cy.log('Check unpublished Submission Marked Registered displays error');
		cy.checkDoiMarkedStatus('Registered', unpublishedSubmissionId, false, 'Unpublished');

		cy.log('Check Submission Marked Stale');
		cy.checkDoiMarkedStatus('Stale', submissionId, true, 'Stale');

		cy.log('Check Submission Marked Unregistered');
		cy.checkDoiMarkedStatus('Unregistered', submissionId, true, 'Unregistered');

		cy.log('Check invalid Submission Marked Stale displays error');
		cy.checkDoiMarkedStatus('Stale', submissionId, false, 'Unregistered');
	});
});
