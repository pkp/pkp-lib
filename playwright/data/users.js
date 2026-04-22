// @ts-check

/**
 * Shared role definitions — single source of truth for test credentials
 * across OJS / OMP / OPS.
 *
 * Consumed by:
 *   - bootstrap.setup.js — "creates users" step seeds accounts
 *   - support/auth.js    — iterates roles to produce storage-state files
 *   - feature specs      — rare; prefer storageState over explicit login
 *
 * Passwords are test-only and match the pattern used by existing Cypress
 * fixtures so migration specs can reuse them unchanged.
 */
exports.users = {
	admin: {
		username: 'admin',
		password: 'admin',
		email: 'admin@example.com',
	},
	editor: {
		username: 'dbarnes',
		password: 'dbarnespassword',
		email: 'dbarnes@mailinator.com',
	},
	author: {
		username: 'lewatkins',
		password: 'lewatkinspassword',
		email: 'lewatkins@mailinator.com',
	},
	reviewer: {
		username: 'agallego',
		password: 'agallegopassword',
		email: 'agallego@mailinator.com',
	},
	subeditor: {
		username: 'rvaca',
		password: 'rvacapassword',
		email: 'rvaca@mailinator.com',
	},
};
