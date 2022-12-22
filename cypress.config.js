const { defineConfig } = require('cypress')

module.exports = defineConfig({
  env: {
    contextTitles: {
      en_US: 'Public Knowledge Preprint Server',
      fr_CA: 'Serveur de prépublication de la connaissance du public',
    },
    contextDescriptions: {
      en_US:
        'The Public Knowledge Preprint Server is a preprint service on the subject of public access to science.',
      fr_CA:
        "Le Serveur de prépublication de la connaissance du public est une service trimestrielle évaluée par les pairs sur le thème de l'accès du public à la science.",
    },
    contextAcronyms: {
      en_US: 'PKP',
    },
    defaultGenre: 'Preprint Text',
    authorUserGroupId: 4,
  },
  watchForFileChanges: false,
  defaultCommandTimeout: 500000,
  video: false,
  numTestsKeptInMemory: 0,
  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require('./lib/pkp/cypress/plugins/index.js')(on, config)
    },
    specPattern: 'cypress/tests/**/*.cy.{js,jsx,ts,tsx}',
  },
  // Allow cypress to interact with iframes
  chromeWebSecurity: false
})
