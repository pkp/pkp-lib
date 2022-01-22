/**
 * @file cypress/tests/integration/Jobs.spec.js
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

 describe('Jobs tests', function() {
    it('Check if Jobs page is alive and with contents', function() {
        cy.login('admin', 'admin', 'publicknowledge');

        // Add two test jobs on queue
        cy.exec('php lib/pkp/tools/jobs.php test');
        cy.exec('php lib/pkp/tools/jobs.php test');

        cy.get('a:contains("Administration")').click();
        cy.get('a:contains("Jobs")').click();
        cy.waitJQuery();

        cy.get('.pkpTable')
          .find('span:contains("queuedTestJob")')
          .should('have.length', 2)
          .should('be.visible');

        cy.exec('php lib/pkp/tools/jobs.php purge --queue=queuedTestJob');
    });
})
