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

    it('Test Failed Jobs page with redispatch, delete, details and requeue all actions', function() {

      cy.login('admin', 'admin', 'publicknowledge');

      // purge all existing jobs in any of the queues
      cy.purgeQueueJobs(null, true);

      // Clear all existing failed jobs
      cy.clearFailedJobs()

      // Add 8 test jobs[successable(4) and failable(4)] on queue
      cy.dispatchTestQueueJobs(4);

      // Run the test jobs in test queue
      cy.runQueueJobs(null, true);

      cy.get('a:contains("Administration")').click();
      cy.get('a:contains("View Failed Jobs")').click();
      cy.waitJQuery();

      // check for 4 failed job rows
      cy.get('.pkpTable')
        .find('span:contains("queuedTestJob")')
        .should('have.length', 4)
        .should('be.visible');

      // Redispatch one failed job
      cy.get('button:contains("Try Again")').first().click();

      // check for 3 failed job rows
      cy.get('.pkpTable')
        .find('span:contains("queuedTestJob")')
        .should('have.length', 3)
        .should('be.visible');

      // Delete one failed job
      cy.get('button:contains("Delete")').first().click();

      // check for 2 failed job rows
      cy.get('.pkpTable')
        .find('span:contains("queuedTestJob")')
        .should('have.length', 2);
      
      // Back to Jobs page
      cy.get('a:contains("Administration")').click();
      cy.get('a:contains("View Jobs")').click();
      cy.waitJQuery();

      // Check for one job in queue which just redispatch from failed job page
      cy.get('.pkpTable')
        .find('span:contains("queuedTestJob")')
        .should('have.length', 1);
      
      // Back to failed jobs page
      cy.get('a:contains("Administration")').click();
      cy.get('a:contains("View Failed Jobs")').click();
      cy.waitJQuery();

      // Check details page of a failed job
      cy.get('a:contains("Details")').first().click();
      cy.get('.pkpTable')
        .find('td:contains("Payload")')
        .should('have.length', 1);
      
      // Back to failed jobs page again
      cy.go('back');
      cy.waitJQuery();

      // Requeue all remaining failed jobs at once
      cy.get('button:contains("Requeue All Failed Jobs")').click();
      cy.wait(2000); // Wait for UI to update and complete ajax request

      // check for 0 failed job rows after requeue all action
      cy.get('.pkpTable')
        .find('span:contains("queuedTestJob")')
        .should('have.length', 0);
      
      // Confirm that 'Requeue All Failed Jobs' button has removed from view
      cy.get('button:contains("Requeue All Failed Jobs")').should('not.exist');

      // Back to Jobs page
      cy.get('a:contains("Administration")').click();
      cy.get('a:contains("View Jobs")').click();
      cy.waitJQuery();

      // Check for 2 more jobs(in totla 3) in queue which just redispatch via requeue all action
      cy.get('.pkpTable')
        .find('span:contains("queuedTestJob")')
        .should('have.length', 3);
      
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
