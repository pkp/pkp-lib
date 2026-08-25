// @ts-check
/**
 * @file lib/pkp/playwright/tests/bootstrap.setup.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The setup project: bring the test installation up to the base seed.
 *
 * Three states, one code path:
 *
 *   no schema      → install it, then seed          (minutes, once per database)
 *   schema, no seed → seed                          (seconds)
 *   already seeded  → one GET and out               (a warm run pays almost nothing)
 *
 * The seed itself is DATA: the app's `playwright/fixtures/bootstrap.js` declares
 * the context, its structure and the 18-user roster, and the PHP endpoint walks
 * the application to that state through its real services. Changing the base
 * seed is therefore a change to a fixture file, never to application code — and
 * because the endpoint is idempotent, every worker may ask for it.
 */

const {execSync} = require('child_process');
const {test, expect} = require('../support/base-test.js');

// A cold run installs the whole schema and runs the migrations.
test.describe.configure({timeout: 15 * 60_000});

test('the shared base context is seeded', async ({
	pkpApi,
	appContext,
	appRoot,
	configFile,
	installCommand,
}) => {
	const contextPath = appContext.seed.contextPath;
	let status = await probeSeeded(pkpApi, contextPath);

	if (!status) {
		// The application cannot answer at all — an empty database. Install the
		// schema, then ask again; a second failure is a real failure.
		installSchema({appRoot, configFile, installCommand});
		status = await probeSeeded(pkpApi, contextPath);

		expect(
			status,
			`The test API is still unreachable after running \`${installCommand}\`.`,
		).toBeTruthy();
	}

	if (status.seeded) {
		// Warm: nothing to do. Every worker takes this path on every later run.
		return;
	}

	const result = await pkpApi.bootstrap(appContext.bootstrapPayload());

	expect(
		result.seeded,
		`Bootstrap did not seed: ${JSON.stringify(result)}`,
	).toBe(true);
	expect(result.urlPath).toBe(contextPath);

	const seededUsernames = Object.keys(result.users ?? {});
	const expectedUsernames = appContext
		.bootstrapPayload()
		.users.map((user) => user.username);

	expect(seededUsernames.sort()).toEqual(expectedUsernames.sort());
});

/**
 * Ask whether the base context exists. Returns null when the application cannot
 * answer — which, on a server that is demonstrably up (the config's readiness
 * probe passed), means the database has no schema yet.
 *
 * @param {import('../support/api.js').PkpApi} pkpApi
 * @param {string} contextPath
 */
async function probeSeeded(pkpApi, contextPath) {
	try {
		return await pkpApi.bootstrapStatus(contextPath);
	} catch (error) {
		console.log(`Base context probe failed: ${error.message.split('\n')[0]}`);

		return null;
	}
}

/**
 * @param {{appRoot: string, configFile: string, installCommand: string}} options
 */
function installSchema({appRoot, configFile, installCommand}) {
	console.log(`Installing the application schema: ${installCommand}`);

	try {
		execSync(installCommand, {
			cwd: appRoot,
			env: {...process.env, PKP_CONFIG_FILE: configFile},
			stdio: 'inherit',
		});
	} catch (error) {
		// An empty database needs no help any more: the tool detects that state
		// before it bootstraps and flips `installed` itself. What is left here is
		// a database that already has tables — a previous install that did not
		// finish — which the tool deliberately refuses to install over.
		throw new Error(
			`${installCommand} failed.\n\n` +
				'`npm run test:e2e:reset` drops and reinstalls the database named in\n' +
				`${configFile}, which recovers from an interrupted install.\n\n` +
				error.message,
		);
	}
}
