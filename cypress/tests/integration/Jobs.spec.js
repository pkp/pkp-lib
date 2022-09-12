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

        // purge all existing jobs in any of the queues
        cy.purgeQueueJobs(null, true);

        // Add two test jobs[successable and failable] on queue
        cy.dispatchTestQueueJobs();

        cy.get('a:contains("Administration")').click();
        cy.get('a:contains("Jobs")').click();
        cy.waitJQuery();

        cy.get('.pkpTable')
          .find('span:contains("queuedTestJob")')
          .should('have.length', 2)
          .should('be.visible');

        // purge all existing jobs in the test queue
        cy.purgeQueueJobs('queuedTestJob');

        cy.reload();
        cy.waitJQuery();

        cy.get('.pkpTable')
          .find('span:contains("queuedTestJob")')
          .should('have.length', 0);
    });
})
