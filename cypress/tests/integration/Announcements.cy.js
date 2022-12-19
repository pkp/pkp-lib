/**
 * @file cypress/tests/integration/Announcements.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

describe('Announcements', function() {
	// Announcement title that will be added
	var newAnnouncementTitle = 'Call for Papers: Making knowledge public in scholarly communications';

	it('Enables announcements and adds an announcement', function() {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/management/settings/website');
		cy.get('button').contains('Setup').eq(0).click();
		cy.get('button').contains('Announcements').click();
		cy.get('label:contains("Enable announcements")').click();
		cy.get('#announcements button').contains('Save').click();
		cy.get('#announcements [role="status"]').contains('Saved');
		// Check that the nav item has been added
		cy.get('.app__navItem:contains("Announcements")');
	});

	it('Adds an announcement', function() {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/management/settings/announcements');

		// Add announcement
		cy.get('button:contains("Add Announcement")').click();
		var title = newAnnouncementTitle;
		var desc = '<p>The Journal of Public Knowledge is issuing a call for papers on making knowledge public in scholarly communications. We are soliciting submissions to be published in a special issue to be published in 2021.</p>';
		cy.wait(500);
		cy.get('#announcement-title-control-en_US').type(title, {delay: 0});
		cy.setTinyMceContent('announcement-descriptionShort-control-en_US', desc);
		cy.setTinyMceContent('announcement-description-control-en_US', desc.repeat(5));
		cy.get('#announcements .pkpForm button').contains('Save').click();
		cy.get('#announcements .listPanel__itemTitle:contains("' + title + '")')
			.parents('.listPanel__itemSummary').find('a').contains('View').click();
		cy.get('h1').contains(title);
	});

	it('Goes to Announcements page', function() {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/announcement');
		cy.get('a:contains(' + newAnnouncementTitle + ')').should('be.visible')
	});

	it('Edits and deletes announcements', function() {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/management/settings/announcements');

		// Add announcement
		cy.get('button:contains("Add Announcement")').click();
		var title = 'Example announcement';
		cy.wait(500);
		cy.get('#announcement-title-control-en_US').type(title, {delay: 0});
		cy.get('#announcements .pkpForm button').contains('Save').click();

		// Edit announcement
		cy.get('#announcements .listPanel__itemTitle:contains("' + title + '")')
			.parents('.listPanel__itemSummary').find('button').contains('Edit').click();
		cy.get('#announcement-title-control-en_US').type('2');
		cy.get('#announcements .pkpForm button').contains('Save').click();
		cy.get('#announcements .listPanel__itemTitle:contains("' + title  + '2")');

		// Delete announcement
		cy.get('#announcements .listPanel__itemTitle:contains("' + title  + '2")')
			.parents('.listPanel__itemSummary').find('button').contains('Delete').click();
		cy.contains('Are you sure you want to permanently delete the announcement ' + title + '2?');
		cy.get('button').contains('Yes').click();
		cy.contains(title).should('not.exist');
	});

	it('Disables announcements', function() {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/management/settings/website');
		cy.get('button').contains('Setup').eq(0).click();
		cy.get('button').contains('Announcements').click();
		cy.get('label:contains("Enable announcements")').click();
		cy.get('#announcements button').contains('Save').click();
		cy.get('#announcements [role="status"]').contains('Saved');
		// Check that the nav item has been removed
		cy.get('.app__navItem:contains("Announcements")').should('not.exist');
	});
});
