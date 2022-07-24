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
		cy.checkDoiAssignment(`${submissionId}-publication-${publicationId}`);
		cy.checkDoiAssignment(`${submissionId}-representation-${galleyId}`);

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

	it('Check Assignment for multi-version submissions', function() {
		const modalSelector = 'div[data-modal^="submission-versionsModal-"]';
		const articleTitle = 'Computer Skill Requirements for New and Existing Teachers: Implications for Policy and Practice';

		loginAndGoToDoiPage();

		cy.log('Check manually adding DOIs for each publication version');

		// Go to multiversion publication and open versions modal
		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button.expander`
		).click();
		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button:contains("View all")`
		).click();

		// Add a DOI for each preprint
		cy.get(`${modalSelector} button:contains("Edit")`).click();
		cy.get(`${modalSelector} div.doiListItem__versionContainer`)
			.each((value, index, collection) => {
				cy.wrap(value).within(() => {
					cy.get('tr:contains("Preprint") input')
						.type(`10.1234/abcd-p${index + 1}`);
				})
			});
		cy.get(`${modalSelector} button:contains("Save")`).click();
		cy.get('.app__notifications')
			.contains(
				'DOI(s) successfully updated',
				{timeout: 20000}
			);

		// Confirm DOIs saved as expected
		cy.get(`${modalSelector} .pkpSpinner`).should('not.exist');
		cy.get(`${modalSelector} div.doiListItem__versionContainer`)
			.each(($el, index, $list) => {
				cy.wrap($el).within(() => {
					cy.get('tr:contains("Preprint") input')
						.should($input => {
							const val = $input.val();
							expect(val).to.contain(`10.1234/abcd-p${index + 1}`);
						});
				})
			});

		cy.log('Check cannot add same DOI to different versions');

		// Try to add existing DOI
		cy.get(`${modalSelector} button:contains("Edit")`).click();
		cy.get(`${modalSelector} div.doiListItem__versionContainer`)
			.first()
			.within(() => {
				cy.get('tr:contains("Preprint") input')
					.type('{selectAll}10.1234/abcd-p2')
			});

		cy.get(`${modalSelector} button:contains("Save")`).click();
		cy.get('.app__notifications')
			.contains(
				'Some DOI(s) could not be updated',
				{timeout: 20000}
			);

		// Confirm original DOI preserved for both versions
		let landingPageLinks = [];
		cy.get(`${modalSelector} .pkpSpinner`).should('not.exist');
		cy.get(`${modalSelector} div.doiListItem__versionContainer`)
			.each(($el, index, $list) => {
				cy.wrap($el).within(() => {
					// Get landing page links for next test while we're at it.
					cy.get('tr:contains("Preprint") input')
						.should($input => {
							const val = $input.val();
							expect(val).to.contain(`10.1234/abcd-p${index + 1}`);
						});
				})
			});

		cy.log('Check submission landing page shows correct DOI for each version');

		// Go to Version 1 page
		cy.get(`${modalSelector} a:contains("Version 1")`).should(($a) => {
			expect($a.attr('target'), 'target').to.be.string('_blank');
			// Chnage target so we can follow the link directory rather than via `cy.visit()`.
			$a.attr('target', '_self');
		}).click();

		cy.get('article .notice').contains('This is an outdated version');

		cy.get('section.item.doi')
			.find('span.value').contains('https://doi.org/10.1234/abcd-p1');

		// Go to Version 2 page
		cy.visit('index.php/publicknowledge/dois');
		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button.expander`
		).click();
		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button:contains("View all")`
		).click();

		cy.get(`${modalSelector} a:contains("Version 2")`).should(($a) => {
			expect($a.attr('target'), 'target').to.be.string('_blank');
			// Chnage target so we can follow the link directory rather than via `cy.visit()`.
			$a.attr('target', '_self');
		}).click();

		cy.get('article .notice').should('not.exist');

		cy.get('section.item.doi')
			.find('span.value').contains('https://doi.org/10.1234/abcd-p2');

		cy.log('Check assign DOIs creates DOIs for only for current version and preserves existing versioned DOIs');
		cy.visit('index.php/publicknowledge/dois');

		// Bulk assign DOIs to submission
		cy.assignDoisByTitle(articleTitle);
		cy.get('#submission-doi-management .pkpSpinner').should('not.exist');

		// Go to multiversion publication and open versions modal
		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button.expander`
		).click();
		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button:contains("View all")`
		).click();

		// Confirm auto assigned DOIs exist alongside manually entered ones
		cy.get(`${modalSelector} div.doiListItem__versionContainer`)
			.each(($el, index, $list) => {
				cy.wrap($el).within(() => {
					cy.get('tr:contains("Preprint") input')
						.should($input => {
							const val = $input.val();
							expect(val).to.contain(`10.1234/abcd-p${index + 1}`);
						});

					cy.get('tr:contains("PDF") input')
						.should($input => {
							switch (index) {
								case 0:
									// Should be empty since not current version
									expect($input.val()).to.have.string('');
									break;
								case 1:
									// Current publication should have auto-generated DOI assigned
									const id = $input.attr('id');
									expect($input.val()).to.match(
										/10.1234\/[0-9abcdefghjkmnpqrstvwxyz]{6}[0-9]{2}/
									);
									break;
							}
						});
				})
			});

		cy.log('Check removing DOI from one version does not remove from another');

		/// Deleting DOI for one version
		cy.get(`${modalSelector} button:contains("Edit")`).click();
		cy.get(`${modalSelector} div.doiListItem__versionContainer`)
			.first()
			.within(() => {
				return cy.get('tr:contains("Preprint") input')
					.type('{selectAll}{backspace}')
			});

		cy.get(`${modalSelector} button:contains("Save")`).click();
		cy.get('.app__notifications')
			.contains(
				'DOI(s) successfully updated',
				{timeout: 20000}
			);

		// Confirm deleted and retained DOI for each version
		cy.get(`${modalSelector} .pkpSpinner`).should('not.exist');
		cy.get(`${modalSelector} div.doiListItem__versionContainer`)
			.each(($el, index, $list) => {
				cy.wrap($el).within(() => {
					return cy.get('tr:contains("Preprint") input')
						.should($input => {
							const val = $input.val();

							switch (index) {
								case 0:
									expect(val).to.have.string('');
									break;
								case 1:
									expect(val).to.contain(`10.1234/abcd-p${index + 1}`);
									break;
							}
						});
				})
			});
	});

	it('Check DOI versioning behaviour in workflow', function() {
		const modalSelector = 'div[data-modal^="submission-versionsModal-"]';
		const articleTitle = 'The Facets Of Job Satisfaction: A Nine-Nation Comparative Study Of Construct Equivalence'

		loginAndGoToDoiPage();

		cy.log("Check DOI versioning off creates new DOI for first version");

		// Turn DOI versioning off
		cy.get('a:contains("Distribution")').click();
		cy.get('button#dois-button').click();

		cy.get("input[name=doiVersioning][value=false]").click();

		cy.get('#doisSetup button')
			.contains('Save')
			.click();
		cy.get('#doisSetup [role="status"]').contains('Saved');

		//Go to publication and rollback to first publication being unpublished, then republish first publication
		cy.get('a:contains("Submissions")').click();
		cy.get('button:contains("Archived")').click();
		cy.get(`div#archive .listPanel__item:contains("${articleTitle}") a:contains("View")`).click();

		cy.get('button#publication-button').click();
		cy.get('button:contains("Unpost")').click();
		cy.get('div[data-modal="confirmUnpublish"] button:contains("Unpost")').click();

		cy.get('button:contains("Unpost")').should('not.exist');

		cy.get('button:contains("Post")').click();
		cy.get('div.pkpWorkflow__publishModal button:contains("Post")').click();

		cy.get('div:contains("This version has been posted and can not be edited.")').should('exist');

		// Confirm DOI created for Version 1
		// First publication assigns DOI as normal
		cy.visit('index.php/publicknowledge/dois');

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button.expander`
		).click();


		// Store values to compare with Version 2 to make sure DOIs were transfered correctly
		// rather than having new ones created
		let unversionedPreprintDoi = '';
		let unversionedPdfDoi = '';

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") tr:contains("Preprint") input`
		).should(($input) => {
			unversionedPreprintDoi = $input.val();
			expect(unversionedPreprintDoi.length).to.be.greaterThan(0);
		});
		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") tr:contains("PDF") input`
		).should(($input) => {
			unversionedPdfDoi = $input.val();
			expect(unversionedPdfDoi.length).to.be.greaterThan(0);
		});

		cy.log("Check DOI versioning off copies previous DOI for subsequent versions");

		// Publish Version 2
		cy.get('a:contains("Submissions")').click();
		cy.get('button:contains("Archived")').click();
		cy.get(`div#archive .listPanel__item:contains("${articleTitle}") a:contains("View")`).click();
		cy.get('button#publication-button').click();

		cy.get('button:contains("Create New Version")').click();
		cy.get('div[data-modal="createVersion"] button:contains("Yes")').click();

		cy.get('button:contains("Post")').click();
		cy.get('div.pkpWorkflow__publishModal button:contains("Post")').click();
		cy.get('div:contains("This version has been posted and can not be edited.")').should('exist');

		// Confirm DOI carried over from Version 1 to Version 2
		cy.visit('index.php/publicknowledge/dois');

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button.expander`
		).click();

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") tr:contains("Preprint") input`
		).should($input => {
			const val = $input.val();
			expect(val).to.contain(unversionedPreprintDoi);
		});
		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") tr:contains("PDF") input`
		).should($input => {
			const val = $input.val();
			expect(val).to.contain(unversionedPdfDoi);
		});

		// Delete galley DOI to confirm DOI deletion is propegated to previous versions when DOI versioning is turned off
		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button:contains("Edit")`
		).click();

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") tr:contains("PDF") input`
		).type('{selectAll}{backspace}');

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button:contains("Save")`
		).click();

		cy.get('.app__notifications')
			.contains(
				'DOI(s) successfully updated',
				{timeout: 20000}
			);

		// Change DOI versioning to "on" to confirm DOI was copied properly
		cy.get('a:contains("Distribution")').click();
		cy.get('button#dois-button').click();

		cy.get("input[name=doiVersioning][value=true]").click();

		// Save
		cy.get('#doisSetup button')
			.contains('Save')
			.click();
		cy.get('#doisSetup [role="status"]').contains('Saved');

		cy.visit('index.php/publicknowledge/dois');

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button.expander`
		).click();

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button:contains("View all")`
		).click();

		// Remove V2 DOIs and confirm unversioned DOIs removed correctly
		cy.get(`${modalSelector} button:contains("Edit")`).click();
		cy.get(`${modalSelector} div.doiListItem__versionContainer`)
			.eq(1)
			.within(($el) => {
				cy.get('tr:contains("Preprint") input')
					.should(($input) => {
						const val = $input.val();
						expect(val.length).to.be.greaterThan(0);
					});
				cy.get('tr:contains("PDF") input')
					.should(($input) => {
						const val = $input.val();
						expect(val).to.equal('');
					});

				// Remove Version 2 DOIs to be able to test DOI versioning
				cy.get('tr:contains("Preprint") input')
					.type('{selectAll}{backspace}');
				cy.get('tr:contains("PDF") input')
					.type('{selectAll}{backspace}');
			});
		cy.get(`${modalSelector} button:contains("Save")`).click();
		cy.get('.app__notifications')
			.contains(
				'DOI(s) successfully updated',
				{timeout: 20000}
			);

		cy.get(`${modalSelector} button:contains("Edit")`).click();
		cy.get(`${modalSelector} div.doiListItem__versionContainer`)
			.eq(0)
			.within(($el) => {
				cy.get('tr:contains("Preprint") input')
					.should(($input) => {
					const val = $input.val();
					expect(val.length).to.be.greaterThan(0);
				});

				cy.get('tr:contains("PDF") input')
					.should(($input) => {
						const val = $input.val();
						expect(val).to.equal('');
					});
				// Add galley DOI back to version 1 to test DOI versioning works correctly
				cy.get('tr:contains("PDF") input')
					.type(unversionedPdfDoi);
			});

		cy.get(`${modalSelector} button:contains("Save")`).click();
		cy.get('.app__notifications')
			.contains(
				'DOI(s) successfully updated',
				{timeout: 20000}
			);

		cy.get(`${modalSelector} button:contains("Close")`).click();

		cy.log("Check DOI versioning on always creates a new DOI and maintains the previous ones");

		// Check creates new version
		// Go to publication and publish Version 2
		cy.get('a:contains("Submissions")').click();
		cy.get('button:contains("Archived")').click();
		cy.get(`div#archive .listPanel__item:contains("${articleTitle}") a:contains("View")`).click();
		cy.get('button#publication-button').click();

		// We have to unpost it first
		cy.get('button:contains("Unpost")').click();
		cy.get('div[data-modal="confirmUnpublish"] button:contains("Unpost")').click();
		cy.get('button:contains("Post")').should('not.exist');

		cy.get('button:contains("Post")').click();
		cy.get('div.pkpWorkflow__publishModal button:contains("Post")').click();
		cy.get('div:contains("This version has been posted and can not be edited.")').should('exist');

		// Confirm "current publication" DOI is new
		cy.visit('index.php/publicknowledge/dois');

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button.expander`
		).click();

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") tr:contains("Preprint") input`
		).should($input => {
			const val = $input.val();
			expect(val.length).to.be.greaterThan(0);
		});

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") tr:contains("PDF") input`
		).should($input => {
			const val = $input.val();
			expect(val.length).to.be.greaterThan(0);
		});

		cy.get(
			`#submission-doi-management .listPanel__item:contains("${articleTitle}") button:contains("View all")`
		).click();

		cy.get(`${modalSelector} div.doiListItem__versionContainer`)
			.each(($el, index, $list) => {
				cy.wrap($el).within(() => {
					switch (index) {
						case 0:
							cy.get('tr:contains("Preprint") input').should($input => {
								const val = $input.val();
								expect(val.length).to.be.greaterThan(0);
								expect(val).to.contain(unversionedPreprintDoi);
							});
							cy.get('tr:contains("PDF") input').should($input => {
								const val = $input.val();
								expect(val.length).to.be.greaterThan(0);
								expect(val).to.contain(unversionedPdfDoi);
							});
							break;
						case 1:
							cy.get('tr:contains("Preprint") input').should($input => {
								const val = $input.val();
								expect(val.length).to.be.greaterThan(0);
								expect(val).to.not.contain(unversionedPreprintDoi);
							});
							cy.get('tr:contains("PDF") input').should($input => {
								const val = $input.val();
								expect(val.length).to.be.greaterThan(0);
								expect(val).to.not.contain(unversionedPdfDoi);
							});
							break;
					}
				});
			});
	});
});
