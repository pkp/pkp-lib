/**
 * @file cypress/tests/integration/Orcid.cy.js
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('ORCID tests', function() {
  it('Enables ORCID', function() {
    cy.login('admin', 'admin');

    cy.visit('index.php/publicknowledge/management/settings/access');
    cy.get('#orcidSettings-button').should('exist').click();

    // Check that the checkbox to enable ORCID is visible, and select it
    cy.get('input[name^="orcidEnabled"]').should('be.visible').check();

    // Check that the form fields are visible
    cy.get('select[name="orcidApiType"]')
      .should('be.visible')
      .select('memberSandbox');
    cy.get('input[name="orcidClientId"]')
      .should('be.visible')
      .clear()
      .type('TEST_CLIENT_ID', {delay: 0});
    cy.get('input[name="orcidClientSecret"]')
      .should('be.visible')
      .clear()
      .type('TEST_SECRET', {delay: 0});
    cy.get('input[name="orcidCity"]').should('be.visible');
    cy.get('input[name="orcidSendMailToAuthorsOnPublication"]')
      .should('be.visible')
      .check();
    cy.get('select[name="orcidLogLevel"]').should('be.visible').select('INFO');
    cy.get('button:contains("Save")').eq(1).should('be.visible').click();

    cy.reload();

    cy.get('input[name="orcidClientId"]')
      .should('be.visible')
      .should('have.value', 'TEST_CLIENT_ID');
  });

  // This should be skipped for OPS & OMP since they are not using the new UI yet

  it('Sends ORCID verification request to author', function() {
    cy.login('dbarnes');
    cy.visit(
      'index.php/publicknowledge/en/dashboard/editorial?currentViewId=assigned-to-me'
    );

    // CLick on first submission in submission list
    cy.get('button[aria-describedby^="submission-title-"]').first().click();

    // TODO Check that the Publications sections is expanded before attempting to click on Contributors
    cy.get('a').contains('Contributors').click();

    cy.get('div.listPanel__itemActions')
      .contains('Primary Contact')
      .parents('div.listPanel__itemActions')
      .within(() => {
        cy.contains('button', 'Edit').click();
      })
      .then(() => {
        // Ensure side modal is opened before continuing
        cy.wait(10000);

        cy.get('[data-cy="sidemodal-header"]').should('be.visible');
        cy.contains('Request verification').click();
        cy.get('button').contains('Yes').click();
        cy.contains(
          'ORCID Verification has been requested! Resend Verification Email'
        ).should('be.visible');
      });
  });

  it('Adds ORCID to user profile', function() {
    cy.login('dbarnes');
    cy.visit('index.php/publicknowledge/en/user/profile');
    cy.window().then((win) => cy.stub(win, 'open').returns({}));

    cy.get('#connect-orcid-button').should('be.visible').click();
  });

  it('Uses ORCID in user registration', function() {
    cy.visit('index.php/publicknowledge/user/register');

    cy.window().then((win) => {
      // Cypress does not work well with multiple tabs or windows
      // instead of displaying the ORCID oauth interface in a new tab, have it load in a hidden iFrame
      // then intercept the call to ORCID and populate form fields similar to the actual implementation.
      cy.stub(win, 'open').callsFake((url) => {
        const iframe = win.document.createElement('iframe');
        iframe.src = url;
        iframe.id = 'iframe';
        iframe.style.display = 'none';
        win.document.body.appendChild(iframe); // Append the iframe to the body

        // The ORCID window would eventually call the app's backend, which responds with html containing a script to update form fields in the parent(opener) window.
        // In this case, we bypass the backend and set the iFrame to have a similar html(with script) content as the app's implementation. This then executes,
        // simulating how the ORCID tab would modify the parent (opener) tab's form fields.
        iframe.srcdoc = `<html><body><script type='text/javascript'>
                  parent.document.getElementById('givenName').value = 'John';
                  parent.document.getElementById("familyName").value = 'Doe';
                  parent.document.getElementById("email").value = 'john.doe@example.com';
                  parent.document.getElementById("country").value = 'JM';
                  parent.document.getElementById("affiliation").value = 'PKP';
                  parent.document.getElementById("orcid").value = 'https://orcid.org/1000-2000-3000-4000';
                  parent.document.getElementById("connect-orcid-button").style.display = "none";
                    </script></body></html>`;
        return iframe.contentWindow;
      });
    });

    // Intercept requests from the iframe (simulating new tab requests)
    cy.intercept('GET', 'https://sandbox.orcid.org/**', {});

    cy.get('#connect-orcid-button').should('be.visible').click();
    // Remove iFrame from DOM
    cy.get('iframe').should('exist').and('not.be.visible');

    // 	Fields should be populated with ORCID data
    cy.get('#givenName').should('have.value', 'John');
    cy.get('#familyName').should('have.value', 'Doe');
    cy.get('#email').should('have.value', 'john.doe@example.com');
    cy.get('select#country').should('have.value', 'JM');
    cy.get('#affiliation').should('have.value', 'PKP');
    cy.get('#orcid').should('have.value', 'https://orcid.org/1000-2000-3000-4000');

    // Populate remaining fields and submit registration form
    cy.get('#username').type('johndoe');
    cy.get('#password').type('superSecretPassword');
    cy.get('#password2').type('superSecretPassword');
    cy.get('input[name="privacyConsent"]').check();

    cy.get('button[type="submit"]').click();

    // 	Navigate to profile and check user data
    cy.visit('/index.php/publicknowledge/en/user/profile');
    cy.get('input[name="givenName[en]"]').should('have.value', 'John');
    cy.get('input[name="familyName[en]"]').should('have.value', 'Doe');
  });

  it('Disables ORCID', function() {
    cy.login('admin', 'admin');

    cy.visit('index.php/publicknowledge/management/settings/access');
    cy.get('#orcidSettings-button').should('exist').click();

    // Check that the checkbox to disbaled ORCID is visible
    cy.get('input[name^="orcidEnabled"]').should('be.visible');

    cy.get('input[name^="orcidEnabled"]').then((checkbox) => {
      if (checkbox.prop('checked')) {
        cy.get('input[name^="orcidEnabled"]').click();

        // Check that the form fields are visible
        cy.get('select[name="orcidApiType"]').should('not.exist');
        cy.get('input[name="orcidClientId"]').should('not.exist');
        cy.get('input[name="orcidClientSecret"]').should('not.exist');
        cy.get('input[name="orcidCity"]').should('not.exist');
        cy.get('input[name="orcidSendMailToAuthorsOnPublication"]').should('not.exist');
        cy.get('select[name="orcidLogLevel"]').should('not.exist');
        cy.get('button:contains("Save")').eq(1).should('be.visible').click();

        // reload to check that data was saved
        cy.reload();
        cy.get('input[name^="orcidEnabled"]').should('not.be.checked');
      }
    });
  });
});
