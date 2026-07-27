// @ts-check
/**
 * @file lib/pkp/playwright/data/users.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The baseline test roster: one account per permission archetype, shared by the
 * three fleets.
 *
 * The roster is ROLE-KEYED: usernames read `role.firstname`, display names read
 * "Firstname Role" so a screenshot says which role acted, emails match
 * usernames, and every first name is unique across the roster. `admin` is
 * created by the installer; the other 17 are seeded by the bootstrap payload in
 * each app's `playwright/fixtures/bootstrap.js`.
 *
 * This file describes WHO exists. Which user groups each one is enrolled in is
 * the app's business and lives in that app's bootstrap payload — the role
 * vocabulary differs per app (OPS has no reviewer group, OMP splits reviewers).
 */

/**
 * @typedef {object} SeededUser
 * @property {string} username
 * @property {string} role     Archetype label, for reading tests — not a user-group key
 * @property {string} givenName
 * @property {string} familyName
 * @property {string} email
 * @property {string} use      When to reach for this account
 */

/** @type {SeededUser[]} */
const users = [
	{
		username: 'admin',
		role: 'siteAdmin',
		givenName: 'Admin',
		familyName: 'User',
		email: 'admin@example.org',
		use: 'Admin console, multi-context operations, plugin management. Created by the installer.',
	},
	{
		username: 'manager.maya',
		role: 'manager',
		givenName: 'Maya',
		familyName: 'Manager',
		email: 'manager.maya@example.org',
		use: 'Context settings, managing users.',
	},
	{
		username: 'editor.diana',
		role: 'editor',
		givenName: 'Diana',
		familyName: 'Editor',
		email: 'editor.diana@example.org',
		use: 'A senior editor of the base context; also assigned to both seeded sections.',
	},
	{
		username: 'sectioneditor.ana',
		role: 'sectionEditor',
		givenName: 'Ana',
		familyName: 'Sectioneditor',
		email: 'sectioneditor.ana@example.org',
		use: 'Section editor for Articles (ART). The default pick for "a section editor".',
	},
	{
		username: 'sectioneditor.ravi',
		role: 'sectionEditor',
		givenName: 'Ravi',
		familyName: 'Sectioneditor',
		email: 'sectioneditor.ravi@example.org',
		use: 'Section editor for Reviews (REV).',
	},
	{
		username: 'sectioneditor.omar',
		role: 'sectionEditor',
		givenName: 'Omar',
		familyName: 'Sectioneditor',
		email: 'sectioneditor.omar@example.org',
		use: 'A second Articles section editor; the designated account for recommend-only assignments (the flag itself is per-assignment).',
	},
	{
		username: 'reviewer.julia',
		role: 'reviewer',
		givenName: 'Julia',
		familyName: 'Reviewer',
		email: 'reviewer.julia@example.org',
		use: 'The default reviewer — use this when you just need "a reviewer".',
	},
	{
		username: 'reviewer.paul',
		role: 'reviewer',
		givenName: 'Paul',
		familyName: 'Reviewer',
		email: 'reviewer.paul@example.org',
		use: 'A second reviewer (several reviews on one submission).',
	},
	{
		username: 'reviewer.amara',
		role: 'reviewer',
		givenName: 'Amara',
		familyName: 'Reviewer',
		email: 'reviewer.amara@example.org',
		use: 'A third reviewer.',
	},
	{
		username: 'reviewer.adam',
		role: 'reviewer',
		givenName: 'Adam',
		familyName: 'Reviewer',
		email: 'reviewer.adam@example.org',
		use: 'A fourth reviewer.',
	},
	{
		username: 'copyeditor.carla',
		role: 'copyeditor',
		givenName: 'Carla',
		familyName: 'Copyeditor',
		email: 'copyeditor.carla@example.org',
		use: 'Copyediting actions.',
	},
	{
		username: 'copyeditor.sam',
		role: 'copyeditor',
		givenName: 'Sam',
		familyName: 'Copyeditor',
		email: 'copyeditor.sam@example.org',
		use: 'A second copyeditor.',
	},
	{
		username: 'layouteditor.leo',
		role: 'layoutEditor',
		givenName: 'Leo',
		familyName: 'Layouteditor',
		email: 'layouteditor.leo@example.org',
		use: 'Layout / galley production.',
	},
	{
		username: 'proofreader.pia',
		role: 'proofreader',
		givenName: 'Pia',
		familyName: 'Proofreader',
		email: 'proofreader.pia@example.org',
		use: 'Proofreading actions.',
	},
	{
		username: 'author.alex',
		role: 'author',
		givenName: 'Alex',
		familyName: 'Author',
		email: 'author.alex@example.org',
		use: 'A non-privileged author. Every other workflow user holds a manager/editor role that short-circuits the metadata-edit permission check, so author-side permission tests need this one.',
	},
	{
		username: 'author.bea',
		role: 'author',
		givenName: 'Bea',
		familyName: 'Author',
		email: 'author.bea@example.org',
		use: 'A second author — co-author and foreign-submission cases.',
	},
	{
		username: 'assistant.rita',
		role: 'assistant',
		givenName: 'Rita',
		familyName: 'Assistant',
		email: 'assistant.rita@example.org',
		use: 'An assistant WITH review-stage access (the Funding coordinator group, stages 1 and 3 — the one default assistant group that reaches external review).',
	},
	{
		username: 'reader.rosa',
		role: 'reader',
		givenName: 'Rosa',
		familyName: 'Reader',
		email: 'reader.rosa@example.org',
		use: 'A registered user with no roles beyond reader — "logged in but no editorial access" gates.',
	},
];

/**
 * The password rule: the username, doubled. `admin` is the installer's account
 * and keeps the installer's password.
 *
 * CAUTION: the login form's password input carries maxlength="32", which three
 * of these passwords exceed. `pages/LoginPage.js` handles that; anything else
 * typing a password into the login form must too.
 *
 * @param {string} username
 * @returns {string}
 */
function getPassword(username) {
	return username === 'admin' ? 'admin' : username + username;
}

/** @type {Record<string, SeededUser>} */
const byUsername = Object.fromEntries(users.map((user) => [user.username, user]));

/**
 * The first seeded user holding each archetype label — "just give me a
 * reviewer". Prefer `appContext.seed.actors` in shared code: an archetype can be
 * absent on an app, and only the app context knows that.
 *
 * @type {Record<string, SeededUser>}
 */
const byRole = users.reduce((map, user) => {
	if (!map[user.role]) {
		map[user.role] = user;
	}

	return map;
}, /** @type {Record<string, SeededUser>} */ ({}));

module.exports = {users, byUsername, byRole, getPassword};
