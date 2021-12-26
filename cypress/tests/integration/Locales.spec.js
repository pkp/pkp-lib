/**
 * @file cypress/tests/integration/Locales.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

 describe('Locales tests', function() {
    it('Checking locale switch operation', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        // Change locale to fr_CA
        cy.get('div.app__userNav > button.pkpButton').click();
        cy.get('div.pkpDropdown__content div.pkpDropdown__section > ul li:contains("Français (Canada)")').click();
        cy.get('a.app__contextTitle').contains('Journal de la connaissance du public');
        // Change locale to en_US
        cy.get('div.app__userNav > button.pkpButton').click();
        cy.get('div.pkpDropdown__content div.pkpDropdown__section > ul li:contains("English")').click();
        cy.get('a.app__contextTitle').contains('Journal of Public Knowledge');
    });
})
