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
        // cy.login('admin', 'admin', 'publicknowledge');
        cy.loginViaForm('abir', 'C0mm0n<>?', 'test-01');

        // purge all existing jobs in any of the queues
        cy.purgeQueueJobs(null, true);

        // Add 2 test jobs[successable and failable] on queue
        cy.dispatchTestQueueJobs();

        cy.get('a:contains("Administration")').click();
        cy.get('a:contains("View Jobs")').click();
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
        
        cy.logout();
    });

    it('Test Failed Jobs page and actions', function() {

      // cy.login('admin', 'admin', 'publicknowledge');
      cy.loginViaForm('abir', 'C0mm0n<>?', 'test-01');

      // purge all existing jobs in any of the queues
      cy.purgeQueueJobs(null, true);

      // Clear all existing failed jobs
      cy.clearFailedJobs()

      // Add 4 test jobs[successable and failable] on queue
      cy.dispatchTestQueueJobs(2);

      // Run the test jobs in test queue
      cy.runQueueJobs(null, true);

      cy.get('a:contains("Administration")').click();
      cy.get('a:contains("View Failed Jobs")').click();
      cy.waitJQuery();

      // check for 2 failed job rows
      cy.get('.pkpTable')
        .find('span:contains("queuedTestJob")')
        .should('have.length', 2)
        .should('be.visible');

      // Redispatch one failed job
      cy.get('button:contains("Redispatch")').first().click();

      // check for 1 failed job rows
      cy.get('.pkpTable')
        .find('span:contains("queuedTestJob")')
        .should('have.length', 1)
        .should('be.visible');

      // Delete one failed job
      cy.get('button:contains("Delete")').click();

      // check for 0 failed job rows
      cy.get('.pkpTable')
        .find('span:contains("queuedTestJob")')
        .should('have.length', 0);
      
      // Back to Jobs page
      cy.get('a:contains("Administration")').click();
      cy.get('a:contains("View Jobs")').click();
      cy.waitJQuery();

      // Check for one job in queue which just redispatch from failed job page
      cy.get('.pkpTable')
        .find('span:contains("queuedTestJob")')
        .should('have.length', 1);
      
      // purge all existing jobs in the test queue
      cy.purgeQueueJobs('queuedTestJob');

      cy.reload();
      cy.waitJQuery();

      cy.get('.pkpTable')
        .find('span:contains("queuedTestJob")')
        .should('have.length', 0);
      
      cy.logout();
  });

})
