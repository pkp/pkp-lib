/**
 * @file lib/pkp/playwright/data/users.js
 *
 * The role-keyed baseline roster shared by all three apps. Identities only —
 * which of these users an app seeds, and with which roles, is decided by that
 * app's playwright/fixtures/bootstrap.js (subset-enrolled per app).
 *
 * Conventions (maintainer decision 2026-07-10):
 * - usernames take the role.firstname form,
 * - display names read "Firstname Role" so UI screenshots say the role,
 * - emails match usernames,
 * - passwords derive from the username (getPassword below).
 *
 * `admin` is created by the installer, not by the bootstrap seed, and is the
 * roster's only site administrator — keep it enabled and unmerged.
 */

const users = [
    {username: 'admin', givenName: 'Site', familyName: 'Admin'},
    {username: 'manager.maya', givenName: 'Maya', familyName: 'Manager'},
    {username: 'editor.diana', givenName: 'Diana', familyName: 'Editor'},
    {username: 'sectioneditor.ana', givenName: 'Ana', familyName: 'Section Editor'},
    {username: 'sectioneditor.ravi', givenName: 'Ravi', familyName: 'Section Editor'},
    {username: 'sectioneditor.omar', givenName: 'Omar', familyName: 'Section Editor'},
    {username: 'reviewer.julia', givenName: 'Julia', familyName: 'Reviewer'},
    {username: 'reviewer.paul', givenName: 'Paul', familyName: 'Reviewer'},
    {username: 'reviewer.amara', givenName: 'Amara', familyName: 'Reviewer'},
    {username: 'reviewer.adam', givenName: 'Adam', familyName: 'Reviewer'},
    {username: 'copyeditor.carla', givenName: 'Carla', familyName: 'Copyeditor'},
    {username: 'copyeditor.sam', givenName: 'Sam', familyName: 'Copyeditor'},
    {username: 'layouteditor.leo', givenName: 'Leo', familyName: 'Layout Editor'},
    {username: 'proofreader.pia', givenName: 'Pia', familyName: 'Proofreader'},
    {username: 'author.alex', givenName: 'Alex', familyName: 'Author'},
    {username: 'author.bea', givenName: 'Bea', familyName: 'Author'},
    {username: 'assistant.rita', givenName: 'Rita', familyName: 'Assistant'},
    {username: 'reader.rosa', givenName: 'Rosa', familyName: 'Reader'},
];

const byUsername = Object.fromEntries(users.map((u) => [u.username, u]));

/**
 * Deterministic password rule: `admin` keeps the installer's password;
 * everyone else is the username doubled. Mind the login form's maxlength=32
 * attribute (LoginPage lifts it before filling).
 *
 * @param {string} username
 * @returns {string}
 */
function getPassword(username) {
    return username === 'admin' ? 'admin' : username + username;
}

/**
 * @param {string} username
 * @returns {string} the roster email for a username
 */
function getEmail(username) {
    return `${username}@mail.test`;
}

module.exports = {users, byUsername, getPassword, getEmail};
