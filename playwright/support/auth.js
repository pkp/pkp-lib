// @ts-check

/**
 * Login + storageState helpers invoked by the bootstrap's final step.
 *
 * For each role in data/users.js, opens a fresh browser context, logs in
 * via the shared LoginPage POM, and writes the resulting cookies +
 * localStorage to playwright/.auth/<role>.json.
 *
 * Feature specs then opt in via:
 *   test.use({storageState: 'playwright/.auth/<role>.json'})
 * and skip login entirely — huge speedup for the parallel feature stage.
 */
exports.saveAuthStates = async function saveAuthStates(
	/* browser, users, outDir */
) {
	throw new Error(
		'TODO: for each role, browser.newContext() -> LoginPage.login() -> context.storageState({path})',
	);
};
