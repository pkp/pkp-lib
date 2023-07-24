/**
 * @file cypress/tests/integration/Plugins.cy.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Plugins tests', function() {
    it('Check if Plugins.xml file was cached', function() {
        cy.intercept('GET', '**/grid/plugins/plugin-gallery-grid/fetch-grid*').as('getPluginList');
        cy.login('dbarnes', null, 'publicknowledge');

        cy.get('a:contains("Website")').click();
        cy.waitJQuery();
        cy.get('button#plugins-button').click();
        cy.get('button#pluginGallery-button').click();
        cy.wait('@getPluginList', {
            responseTimeout: 30000
        }).then((evt) => {
            cy.readFile('cache/fc-loadPluginsXML-0.php');
        });
    });
})
