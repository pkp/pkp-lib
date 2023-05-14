/**
 * @file cypress/tests/data/50-SubmissionGroups.cy.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Data suite tests', function() {
	it('Creates/configures sections', function() {
		cy.login('admin', 'admin');
		cy.get('a').contains('admin').click();
		cy.get('a').contains('Dashboard').click();
		cy.get('.app__nav a').contains('Server').click();
		cy.get('button[id="sections-button"]').click();

		// Edit Preprints section to add section editors
		cy.get('div#sections a[class=show_extras]').click();
		cy.get('a[id^=component-grid-settings-sections-sectiongrid-row-1-editSection-button-]').click();
		cy.wait(1000); // Avoid occasional failure due to form init taking time
		cy.get('label').contains('David Buskins').click();
		cy.get('label').contains('Stephanie Berardo').click();
		cy.get('form[id=sectionForm]').contains('Save').click();

	});
	it('Creates/configures categories', function() {
		cy.login('admin', 'admin');
		cy.get('a').contains('admin').click();
		cy.get('a').contains('Dashboard').click();
		cy.get('.app__nav a').contains('Server').click();
		cy.get('button[id="categories-button"]').click();

		cy.addCategory('History', 'history');
		cy.addCategory('Biology', 'biology');
		cy.addCategory('Social sciences', 'social-sciences');
		cy.addCategory('Mathematics', 'mathematics');

		// Create a Cultural History subcategory
		cy.get('a[id^=component-grid-settings-category-categorycategorygrid-addCategory-button-]').click();
		cy.wait(1000); // Avoid occasional failure due to form init taking time
		cy.get('input[id^="name-en-"]').type('Cultural History', {delay: 0});
		cy.get('select[id="parentId"],select[id="parentId"]').select('History');
		cy.get('input[id^="path-"]').type('cultural-history', {delay: 0});
		cy.get('form[id=categoryForm]').contains('OK').click();

	});
})
