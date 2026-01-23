/**
 * @file cypress/tests/integration/publicComents/PublicComments.cy.js
 *
 * copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

// Helper method to open a comment's side modal from comments table
function openComment(commentText) {
	cy.contains('tr', commentText)
		.find('button[aria-label="More Actions"]')
		.click({force: true})
		.then(() => {
			cy.get('[role="menuitem"]:contains("View Comment")')
				.click({force: true});
		});
}

const testCommentText = 'Test comment';
const testReportText = 'Inappropriate content';

describe('Public Comments Tests', function() {

	// Full public comments feature is only available in OJS
	if (Cypress.env('defaultGenre') !== 'Article Text') {
		return;
	}

	it('should enable public commenting', () => {
		cy.login('rvaca');

		cy.visit('http://localhost:8000/index.php/publicknowledge/en/management/settings/website#content');
		cy.get('[role="tab"]').contains('Comments').click();
		cy.get('[name="enablePublicComments"]').check();
		cy.get('button:visible').contains('Save').click();
	});

	it('should allow logged in users to create a comment on the latest version of the publication', () => {
		const usersToComment = [
			'jnovak',
			'kalkhafaji',
			'ckwantes'
		];

		usersToComment.forEach((user, index) => {
			cy.login(user);
			cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');

			cy.contains('Version of Record 1.0')
				.parent();
			cy.get('.pkpComments__new-input').find('textarea')
				.should('be.visible').type(`${testCommentText} - ${index + 1}`);
			cy.contains('button.pkpComments__new-submit', 'Submit').click();

			// Verify that the user's comment is displayed to them along with note that the comment needs approval
			cy.get('.pkpComments__notification-needs-approval')
				.should('contain.text', 'Your comment will be visible when the editor approves it')
				.parent()
				.find('.pkpComments__message-body')
				.should('contain.text', `${testCommentText} - ${index + 1}`);

			cy.logout();
		});
	});

	it('should allow moderator to approve a comment', () => {
		cy.login('rvaca');
		const commentText = `${testCommentText} - 3`;
		cy.visit('index.php/publicknowledge/en/management/settings/userComments#needsApproval');

		cy.contains('tr', commentText).should('contain.text', 'Hidden/Needs Approval');
		openComment(commentText);
		cy.contains('button', 'Approve Comment').click();

		// Comment should no longer be in the Hidden/Needs Approval tab
		cy.contains('tr', commentText).should('not.exist');

		// Comment should now be in the Approved tab
		cy.contains('button', 'Approved').click();
		cy.contains('tr', commentText).should('exist');
	});

	it('should allow unauthenticated users to view comments', () => {
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');

		cy.contains('Version of Record 1.0')
			.parent()
			.get('.pkpComments__message-body').contains(`${testCommentText} - 3`)
			.should('be.visible');
	});

	it('should allow authenticated users to view comments', () => {
		cy.login('eostrom');
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');

		cy.contains('Version of Record 1.0')
			.parent()
			.get('.pkpComments__message-body').contains(`${testCommentText} - 3`)
			.should('be.visible');
	});

	it('should allow authenticated user to report a comment', () => {
		cy.login('eostrom');
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');

		cy.contains('Version of Record 1.0')
			.parent()
			.get('.pkpComments__message-body').contains(`${testCommentText} - 3`)
			.parent()
			.find('div.pkpComments__message-header button')
			.click();

		cy.get('[role="menuitem"]:contains("Report")').click();

		cy.get('.pkpCommentReportDialog')
			.should('contain.text', 'Report the following comment by Catherine Kwantes')
			.and('contain.text', `${testCommentText} - 3`)
			.and('contain.text', 'Please tell us why you want to report this comment');

		cy.get('.pkpCommentReportDialog')
			.find('.pkpCommentReportDialog__reason-input')
			.find('textarea')
			.type(`${testReportText} - 1`);

		cy.get('div.pkpCommentReportDialog')
			.parent()
			.get('div.BaseDialogActionButtons')
			.contains('button', 'Submit').click();
	});

	it('should not allow user to report their own comment', () => {
		cy.login('jnovak');
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');

		cy.contains('Version of Record 1.0')
			.parent()
			.get('.pkpComments__message-body')
			.contains(`${testCommentText} - 1`)
			.parent()
			.find('div.pkpComments__message-header button')
			.click();

		cy.get('[role="menuitem"]:contains("Report")').should('not.exist');
	});

	it('should not allow unauthenticated user to delete or report comment', () => {
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');

		cy.contains('Version of Record 1.0')
			.parent()
			.get('.pkpComments__message-body')
			.contains(`${testCommentText} - 3`)
			.parent()
			// Report and delete options should not exist
			.find('div.pkpComments__message-header button').should('not.exist');
	});

	it('should not allow authenticated user to see delete option on comment they did not create', () => {
		cy.login('jnovak');
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');

		cy.contains('Version of Record 1.0')
			.parent()
			.get('.pkpComments__message-body')
			.contains(`${testCommentText} - 3`)
			.parent()
			.find('div.pkpComments__message-header button')
			.click();

		cy.get('[role="menuitem"]:contains("Delete Comment")').should('not.exist');
	});

	it('should allow authenticated user to delete their own comment', () => {
		cy.login('jnovak');
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');

		cy.contains('Version of Record 1.0')
			.parent()
			.get('.pkpComments__message-body')
			.contains(`${testCommentText} - 1`)
			.parent()
			.find('div.pkpComments__message-header button')
			.click();

		cy.get('[role="menuitem"]:contains("Delete Comment")').click();
		cy.contains('.BaseDialogBody', 'Are you sure you want to delete the following comment?').should('exist');
		cy.contains('.BaseDialogBody', `${testCommentText} - 1`).should('exist');
		cy.get('.BaseDialogActionButtons').contains('button', 'Delete').click();

		cy.contains(`${testCommentText} - 1`).should('not.exist');
	});

	it('should allow the user to click the \'All comments\' button, which takes them to the section of the page that has the comments.', () => {
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');

		let rect = null;
		let isInViewport = false;

		cy.get('section.comments').then($el => {
			 rect = $el[0].getBoundingClientRect();
			 isInViewport = (
				rect.top >= 0 &&
				rect.left >= 0 &&
				rect.bottom <= Cypress.config('viewportHeight') &&
				rect.right <= Cypress.config('viewportWidth')
			);

			// If comments section is not in viewport, click the "All Comments" button to scroll to that section of the page
			if (!isInViewport) {
				cy.contains('a', 'All Comments').click();
				cy.wait(500);

				cy.get('section.comments').then($el => {
					 rect = $el[0].getBoundingClientRect();
					 isInViewport = (
						rect.top >= 0 &&
						rect.left >= 0 &&
						rect.bottom <= Cypress.config('viewportHeight') &&
						rect.right <= Cypress.config('viewportWidth')
					);

					expect(isInViewport).to.be.true;
				});
			}
		});
	});

	it('should bring unauthenticated user through login flow before seeing comment form', () => {
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');
		cy.get('.pkpComments__new-input textarea').should('not.exist');

		cy.contains('.pkpScrollToComments__log-into', 'Log in to comment').click();
		cy.url().should('include', '/login');
		cy.get('input[name="username"]').type('eostrom');
		cy.get('input[name="password"]').type('eostromeostrom');
		cy.get('button[type="submit"]').click();

		cy.get('.pkpComments__new-input').find('textarea').should('exist');
	});

	it('should allow moderator to view a comment', () => {
		cy.login('rvaca');
		cy.visit('index.php/publicknowledge/en/management/settings/userComments');
		const commentText = `${testCommentText} - 3`;

		cy.contains('tr', commentText).should('exist');

		openComment(commentText);

		cy.contains('Comment preview').should('exist');

		cy.get('div[role="dialog"]')
			.should('contain.text', 'View comment details by')
			.and('contain.text', 'Catherine Kwantes')
			.and('contain.text', commentText)
			.and('contain.text', 'Comment preview')
			.and('contain.text', 'This comment was approved');

		cy.get('div[role="dialog"]').within(() => {
			cy.contains('button', 'Delete Comment').should('exist');
			cy.contains('button', 'Delete Comment').should('exist');
			cy.contains('button', 'Hide Comment').should('exist');
		});
	});

	it('should allow moderator to view reports for a comment', () => {
		cy.login('rvaca');
		const commentText = `${testCommentText} - 3`;
		cy.visit('index.php/publicknowledge/en/management/settings/userComments');

		cy.contains('tr', commentText).should('exist');

		openComment(commentText);

		cy.get('div[role="dialog"]').within(() => {
			cy.contains('Reports').should('be.visible');
			cy.contains('This is the list of all the users who have reported this comment').should('be.visible');

			// Open report
			cy.get('table tr').should('have.length.greaterThan', 0);
			cy.contains('tr', `${testReportText} - 1`)
				.find('button[aria-label="More Actions"]')
				.click({force: true})
				.then(() => {
					cy.get('[role="menuitem"]:contains("View Report")')
						.click({force: true});
				});
		});
		cy.contains('View report details by');
		cy.contains('Elinor Ostrom');
		cy.contains('Report preview');
		cy.contains(`${testReportText} - 1`);
		cy.contains('Indiana University');
		cy.contains('button', 'Delete Report').should('be.visible');
	});

	it('should show only reported comments when moderator views comments under Reported tab', () => {
		cy.login('rvaca');

		cy.visit('index.php/publicknowledge/en/management/settings/userComments#reported');

		cy.contains('Loading', { timeout: 20000 }).should('not.exist');
		cy.get('table tbody tr')
			.not(':contains("No Items")')
			.should('have.length.greaterThan', 0).each(($row) => {
				cy.wrap($row).should('contain.text', 'Reported');
		});
	});

	it('should show only approved comments when moderator views comments under Approved tab', () => {
		cy.login('rvaca');
		cy.visit('index.php/publicknowledge/en/management/settings/userComments#approved');
		
		cy.contains('Loading', { timeout: 20000 }).should('not.exist');
		cy.get('table tbody tr')
			.not(':contains("No Items")')
			.should('have.length.greaterThan', 0).each(($row) => {
				cy.wrap($row).should('contain.text', 'Approved');
		});
	});

	it('should show only unapproved comments when moderator views comments under Needs Approval tab', () => {
		cy.login('rvaca');
		cy.visit('index.php/publicknowledge/en/management/settings/userComments#needsApproval');

		cy.contains('Loading', { timeout: 20000 }).should('not.exist');
		cy.get('table tbody tr')
			.not(':contains("No Items")')
			.should('have.length.greaterThan', 0).each(($row) => {
				cy.wrap($row).should('contain.text', 'Hidden/Needs Approval');
		});
	});

	it('should allow moderator to delete report via side modal', () => {
		cy.login('rvaca');
		const commentText = `${testCommentText} - 3`;
		cy.visit('index.php/publicknowledge/en/management/settings/userComments');

		openComment(commentText);
		cy.wait(500);

		cy.get('div[role="dialog"]').within(() => {
			cy.contains('Reports').should('be.visible');

			cy.contains('tr', `${testReportText} - 1`)
				.find('button[aria-label="More Actions"]')
				.click({force: true})
				.then(() => {
					cy.get('[role="menuitem"]:contains("View Report")')
						.click({force: true});
				});
		});
		cy.contains('button', 'Delete Report').click();
		cy.contains('Are you sure you want to delete this report? This action cannot be undone.').should('be.visible');
		cy.get('div[title="Delete Report"]').contains('button', 'Delete').click({force: true});

		cy.contains('tr', `${testReportText} - 1`).should('not.exist');
	});

	it('should allow moderator to delete a report via table row actions', () => {
		const commentText = `${testCommentText} - 3`;

		//First have a user report the comment
		cy.login('eostrom');
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');

		cy.contains('a', 'All Comments').click();

		cy.contains('Version of Record 1.0')
			.parent()
			.get('.pkpComments__message-body')
			.contains(commentText)
			.parent()
			.find('div.pkpComments__message-header button')
			.click();

		cy.get('[role="menuitem"]:contains("Report")').click();

		cy.get('.pkpCommentReportDialog__reason-input')
			.find('textarea')
			.type(`${testReportText} - 2`);

		cy.get('div.pkpCommentReportDialog')
			.parent()
			.get('div.BaseDialogActionButtons')
			.contains('button', 'Submit').click();

		cy.logout();

		// Then login as moderator and delete the report
		cy.login('rvaca');
		cy.visit('index.php/publicknowledge/en/management/settings/userComments');

		openComment(commentText);

		cy.get('div[role="dialog"]').within(() => {
			cy.contains('Reports').should('be.visible');

			cy.contains('tr', `${testReportText} - 2`)
				.find('button[aria-label="More Actions"]')
				.click({force: true})
				.then(() => {
					cy.get('[role="menuitem"]:contains("Delete Report")')
						.click({force: true});
				});
		});

		cy.contains('Are you sure you want to delete this report? This action cannot be undone.').should('be.visible');
		cy.get('div[title="Delete Report"]').contains('button', 'Delete').click({force: true});

		cy.contains('tr', `${testReportText} - 2`).should('not.exist');
	});

	it('should allow moderator to hide a comment', () => {
		cy.login('rvaca');
		cy.visit('index.php/publicknowledge/en/management/settings/userComments#approved');
		const commentText = `${testCommentText} - 3`;
		cy.contains('tr', commentText).should('exist');

		openComment(commentText);

		cy.get('div[role="dialog"]').within(() => {
			cy.contains('button', 'Hide Comment').should('exist').click();
		});

		// Comment should no longer be in approved tab
		cy.contains('tr', commentText).should('not.exist');

		// Check that comment is now in Hidden/Needs Approval tab
		cy.get('button[role="tab"]').contains('Hidden/Needs Approval').click();
		// cy.wait(500);

		cy.contains('tr', commentText)
			.should('exist')
			.within(() =>
				cy.contains('Hidden/Needs Approval').should('exist')
			);
	});

	it('should allow moderator to delete comment via table row actions', () => {
		cy.login('rvaca');
		cy.visit('index.php/publicknowledge/en/management/settings/userComments');

		cy.contains('tr', `${testCommentText} - 3`).should('exist');

		cy.contains('tr', `${testCommentText} - 3`)
			.find('button[aria-label="More Actions"]')
			.click({force: true})
			.then(() => {
				cy.get('[role="menuitem"]:contains("Delete Comment")')
					.click({force: true});
			});

		cy.wait(500);
		cy.contains('Are you sure you want to delete this comment? This action cannot be undone.').should('be.visible');
		cy.contains('button', 'Delete').click({force: true});
		cy.contains('tr', `${testCommentText} - 3`).should('not.exist');
	});

	it('should allow moderator to delete a comment via side modal', () => {
		cy.login('rvaca');
		const commentText = `${testCommentText} - 2`;
		cy.visit('index.php/publicknowledge/en/management/settings/userComments');

		cy.contains('tr', commentText).should('exist');
		openComment(commentText);

		cy.get('div[role="dialog"]').within(() =>
			cy.contains('button', 'Delete Comment')
				.should('be.visible')
				.click()
		);

		cy.contains('Are you sure you want to delete this comment? This action cannot be undone.').should('be.visible');
		cy.get('div[title="Delete Comment"]').contains('button', 'Delete').click({force: true});
		cy.contains('tr', commentText).should('not.exist');
	});

	it('publish a new publication version, then verify that comments are not allowed on previous version', () => {
		cy.login('rvaca');
		cy.visit('/index.php/publicknowledge/workflow/access/1');
		cy.openWorkflowMenu('Version of Record 1.1', 'Title & Abstract');
		// Publish version
		cy.get('button').contains('Publish').click();

		// Wait for form to be properly populated
		cy.wait(1000);
		cy.get('div[data-cy="active-modal"]')
			.should('contain.text', 'Review Publishing Details')
			.get('button').contains('Confirm').click();

		cy.get('.pkpWorkflow__publishModal button').contains('Publish').click();

		// Navigate to publication and check that previous version has 'closed discussion' note, and does not have comment form
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');
		cy.contains('a', 'All Comments').click();

		cy.contains('div[role="region"]', 'Discussion is closed on this version, please comment on the latest version above.')
			.should('not.exist');

		// Check that comment section for both versions exists
		cy.contains('button', 'Version of Record 1.0').click(); // Old version
		cy.contains('button', 'Version of Record 1.1').click(); // Latest Version

		// Check that discussion is closed on old version
		cy.contains('Version of Record 1.0').click()
			.parent()
			.get('.pkpComments__notification-not-latest')
			.contains('Discussion is closed on this version, please comment on the latest version above.')
			.should('be.visible');

		// Verify that latest version has comment form
		cy.contains('Version of Record 1.1')
			.click()
			.parent()
			.get('.pkpComments__new-input').find('textarea')
			.should('be.visible');

		// Check that publication versions are displayed in correct order (latest version first)
		cy.get('span.pkpComments__version-header-label').then($els => {
			expect($els[0]).to.contain.text('Version of Record 1.1');
			expect($els[1]).to.contain.text('Version of Record 1.0');
		});

		// Cleanup - unpublish new version so it doesn't interfere with other tests
		cy.visit('/index.php/publicknowledge/workflow/access/1');
		cy.openWorkflowMenu('Version of Record 1.1', 'Title & Abstract');
		cy.get('button').contains('Unpublish').click();
		cy.get('[data-cy="dialog"] button').contains('Unpublish').click();
	});

	it('should disable public commenting', () => {
		cy.login('rvaca');

		cy.visit('http://localhost:8000/index.php/publicknowledge/en/management/settings/website#content');
		cy.get('[role="tab"]').contains('Comments').click();
		cy.get('[name="enablePublicComments"]').uncheck();
		cy.get('button:visible').contains('Save').click();

		cy.wait(500)

		// Verify that the comment area does not exist
		cy.visit('index.php/publicknowledge/en/article/view/mwandenga-signalling-theory');
		cy.get('#pkpUserCommentsContainer').should('not.exist');
		cy.contains('Comment on this publication').should('not.exist');
	});
});
