// @ts-check

/**
 * Shared baseline users — single source of truth for Playwright test
 * credentials. Mirrors the Cypress baseline at
 * cypress/tests/data/10-ApplicationSetup/40-CreateUsers.cy.js so tests
 * can be ported with no credential churn.
 *
 * Passwords follow the Cypress rule (lib/pkp/cypress/support/commands.js:20):
 *   admin          → 'admin'
 *   everyone else  → username + username  (e.g. dbarnes → dbarnesdbarnes)
 *
 * Consumed by:
 *   - bootstrap.setup.js — POSTs the users list (with passwords) inside
 *                          the baseline journal spec to
 *                          /api/v1/_test/scenarios/journal
 *   - support/auth.js    — iterates roles to save storageState files
 *   - feature specs      — rarely; prefer storageState over explicit login
 */

/**
 * Cypress-compatible password rule. Kept as a tiny exported helper so both
 * the Playwright client and any shared tooling derive passwords the same way.
 *
 * @param {string} username
 * @returns {string}
 */
exports.getPassword = function getPassword(username) {
	if (!username) {
		throw new Error('getPassword: username is required');
	}
	return username === 'admin' ? 'admin' : username + username;
};

/**
 * @typedef {Object} BaselineUser
 * @property {string} username
 * @property {string} givenName
 * @property {string} familyName
 * @property {string} email
 * @property {string} country           ISO-3166 alpha-2
 * @property {string} affiliation
 * @property {string=} journal          urlPath of the journal the roles apply to
 * @property {string[]=} roles          role-string keys understood by UserProcessor
 * @property {boolean=} siteAdmin
 * @property {boolean=} mustChangePassword
 */

/**
 * Ordered baseline. The order matches the Cypress data file for reviewability.
 * Admin is created by the installer; listed here for reference only.
 *
 * @type {BaselineUser[]}
 */
exports.baselineUsers = [
	{
		username: 'admin',
		givenName: 'Admin',
		familyName: 'User',
		email: 'admin@example.com',
		country: 'US',
		affiliation: 'Public Knowledge Project',
		siteAdmin: true,
	},
	{
		username: 'rvaca',
		givenName: 'Ramiro',
		familyName: 'Vaca',
		email: 'rvaca@mailinator.com',
		country: 'MX',
		affiliation: 'Universidad Nacional Autónoma de México',
		journal: 'publicknowledge',
		roles: ['manager'],
		mustChangePassword: true,
	},
	{
		username: 'dbarnes',
		givenName: 'Daniel',
		familyName: 'Barnes',
		email: 'dbarnes@mailinator.com',
		country: 'AU',
		affiliation: 'University of Melbourne',
		journal: 'publicknowledge',
		roles: ['editor'],
	},
	{
		username: 'dbuskins',
		givenName: 'David',
		familyName: 'Buskins',
		email: 'dbuskins@mailinator.com',
		country: 'US',
		affiliation: 'University of Chicago',
		journal: 'publicknowledge',
		roles: ['sectionEditor'],
	},
	{
		username: 'sberardo',
		givenName: 'Stephanie',
		familyName: 'Berardo',
		email: 'sberardo@mailinator.com',
		country: 'CA',
		affiliation: 'University of Toronto',
		journal: 'publicknowledge',
		roles: ['sectionEditor'],
	},
	{
		username: 'minoue',
		givenName: 'Minoti',
		familyName: 'Inoue',
		email: 'minoue@mailinator.com',
		country: 'JP',
		affiliation: 'Kyoto University',
		journal: 'publicknowledge',
		roles: ['sectionEditor'],
	},
	{
		username: 'jjanssen',
		givenName: 'Julie',
		familyName: 'Janssen',
		email: 'jjanssen@mailinator.com',
		country: 'NL',
		affiliation: 'Utrecht University',
		journal: 'publicknowledge',
		roles: ['reviewer'],
	},
	{
		username: 'phudson',
		givenName: 'Paul',
		familyName: 'Hudson',
		email: 'phudson@mailinator.com',
		country: 'CA',
		affiliation: 'McGill University',
		journal: 'publicknowledge',
		roles: ['reviewer'],
	},
	{
		username: 'amccrae',
		givenName: 'Aisla',
		familyName: 'McCrae',
		email: 'amccrae@mailinator.com',
		country: 'CA',
		affiliation: 'University of Manitoba',
		journal: 'publicknowledge',
		roles: ['reviewer'],
	},
	{
		username: 'agallego',
		givenName: 'Adela',
		familyName: 'Gallego',
		email: 'agallego@mailinator.com',
		country: 'US',
		affiliation: 'State University of New York',
		journal: 'publicknowledge',
		roles: ['reviewer'],
	},
	{
		username: 'mfritz',
		givenName: 'Maria',
		familyName: 'Fritz',
		email: 'mfritz@mailinator.com',
		country: 'BE',
		affiliation: 'Ghent University',
		journal: 'publicknowledge',
		roles: ['copyeditor'],
	},
	{
		username: 'svogt',
		givenName: 'Sarah',
		familyName: 'Vogt',
		email: 'svogt@mailinator.com',
		country: 'CL',
		affiliation: 'Universidad de Chile',
		journal: 'publicknowledge',
		roles: ['copyeditor'],
	},
	{
		username: 'gcox',
		givenName: 'Graham',
		familyName: 'Cox',
		email: 'gcox@mailinator.com',
		country: 'US',
		affiliation: 'Duke University',
		journal: 'publicknowledge',
		roles: ['layoutEditor'],
	},
	{
		username: 'shellier',
		givenName: 'Stephen',
		familyName: 'Hellier',
		email: 'shellier@mailinator.com',
		country: 'ZA',
		affiliation: 'University of Cape Town',
		journal: 'publicknowledge',
		roles: ['layoutEditor'],
	},
	{
		username: 'cturner',
		givenName: 'Catherine',
		familyName: 'Turner',
		email: 'cturner@mailinator.com',
		country: 'GB',
		affiliation: 'Imperial College London',
		journal: 'publicknowledge',
		roles: ['proofreader'],
	},
	{
		username: 'skumar',
		givenName: 'Sabine',
		familyName: 'Kumar',
		email: 'skumar@mailinator.com',
		country: 'SG',
		affiliation: 'National University of Singapore',
		journal: 'publicknowledge',
		roles: ['proofreader'],
	},
	{
		// Non-privileged author user — exists so specs that need to exercise
		// the author-side permission gate (Repo::submission()->canEditPublication
		// for an AUTHOR-only stage assignment) have a functional login. The
		// other publicknowledge users either bypass the gate via a manager/
		// editor role (NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES) or have
		// mustChangePassword=true so login redirects to the password-change
		// form before reaching any workflow page. atester is the answer:
		// author-only, password derives to 'atesteratester' via getPassword,
		// no mustChangePassword. See author-edit-published.spec.js (row #43).
		username: 'atester',
		givenName: 'Author',
		familyName: 'Tester',
		email: 'atester@mailinator.com',
		country: 'CA',
		affiliation: 'Test Affiliation',
		journal: 'publicknowledge',
		roles: ['author'],
		mustChangePassword: false,
	},
];

/**
 * Convenience map keyed by role string → first baseline user with that role.
 * Useful for tests that want "some editor" without caring which one.
 */
exports.users = exports.baselineUsers.reduce((acc, user) => {
	if (user.siteAdmin && !acc.admin) {
		acc.admin = user;
	}
	for (const role of user.roles ?? []) {
		if (!acc[role]) {
			acc[role] = user;
		}
	}
	return acc;
}, /** @type {Record<string, BaselineUser>} */ ({}));
