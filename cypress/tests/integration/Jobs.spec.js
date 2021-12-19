/**
 * @file cypress/tests/integration/Jobs.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

 describe('Jobs tests', function() {
    it('Check if Jobs page is alive and with contents', function() {
        cy.login('dbarnes', null, 'publicknowledge');

        // Add two test jobs on queue
        cy.exec('php lib/pkp/tools/jobs.php test');
        cy.exec('php lib/pkp/tools/jobs.php test');

        cy.get('a:contains("Jobs")').click();
        cy.waitJQuery();

        cy.contains('There\'s a total of 2 job(s) on the queue').should('be.visible');

        cy.exec('php lib/pkp/tools/jobs.php purge --all');
    });
})
