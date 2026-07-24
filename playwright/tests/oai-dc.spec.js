// @ts-check
const {test, expect} = require('../support/base-test.js');
const submissionPublished = require('../../../../playwright/fixtures/scenarios/submission-published.js');

/**
 * OAI — Dublin Core endpoint — row #39 in docs/e2e-playwright-migration.md.
 *
 * Ports lib/pkp/cypress/tests/integration/oai/DC.cy.js. That Cypress test
 * runs late in the serial suite and relies on previously-seeded published
 * submissions to have left records behind. Each test here seeds its own
 * published submission via `submissionPublished(tag)` so parallel workers
 * don't race on a shared records list and so the spec doesn't depend on
 * test-ordering.
 *
 * Lives in lib/pkp even though it uses a scenario fixture from the OJS
 * side (`playwright/fixtures/scenarios/submission-published.js`) — OAI
 * is a shared PKP capability (OMP + OPS expose the same endpoint with
 * the same DC metadata shape), so the spec home follows the endpoint.
 * When OMP/OPS port their own `submissionPublished` scenario fixtures
 * this import can be abstracted behind a lib/pkp fixture seam; for now
 * the direct reach-out is the cheapest way to stay DRY.
 *
 * XML shape (exercise):
 *   - Dublin Core: <oai_dc:dc> wrapping <dc:title>, <dc:creator>,
 *     <dc:identifier>, <dc:source>, <dc:language>, etc. See
 *     lib/pkp/plugins/oaiMetadataFormats/dc/OAIMetadataFormat_DC.php for
 *     the full emission list.
 *   - Record identifier: `oai:{repositoryId}:article/{articleId}` — see
 *     classes/oai/ojs/JournalOAI.php::articleIdToIdentifier. We extract
 *     the identifier from ListRecords and feed it back to GetRecord
 *     rather than assuming a specific repository_id value.
 */
test.describe('OAI Dublin Core endpoint', () => {
	test(
		'ListRecords with metadataPrefix=oai_dc returns DC records for published items',
		async ({pkpApi, request}) => {
			const tag = uniqueTag(test.info(), 'dc-list');
			await pkpApi.createSubmission(submissionPublished({tag}));

			// The OAI endpoint is unauthenticated + session-less — every
			// request is served by OAIHandler::index, which calls
			// PKPSessionGuard::disableSession(). We use the test's baseline
			// APIRequestContext without any storageState juggling.
			const res = await request.get(
				'/index.php/publicknowledge/oai?verb=ListRecords&metadataPrefix=oai_dc',
			);
			expect(res.status()).toBe(200);
			expect(res.headers()['content-type']).toContain('text/xml');

			const body = await res.text();

			// ListRecords wrapper present (vs. OAI error document).
			expect(body).toContain('<ListRecords>');

			// At least one record element exists. The OAI spec sorts
			// records by date so we can't predict where ours lands; the
			// existence of any <record> proves published content is
			// flowing through the endpoint.
			const recordMatches = body.match(/<record>[\s\S]*?<\/record>/g) ?? [];
			expect(recordMatches.length).toBeGreaterThan(0);

			// Every record carries a Dublin Core metadata block with at
			// minimum a title + creator — the two fields OAIMetadataFormat_DC
			// always emits for a published article, and the two the old
			// Cypress suite hard-asserted as "every record must have". DC
			// elements carry an xml:lang attribute in this emitter, so
			// match the opening tag as `<dc:TAG` followed by optional
			// attributes and a `>`.
			expect(body).toMatch(/<oai_dc:dc[\s\S]*?<\/oai_dc:dc>/);
			expect(body).toMatch(/<dc:title[^>]*>[^<]+<\/dc:title>/);
			expect(body).toMatch(/<dc:creator[^>]*>[^<]+<\/dc:creator>/);

			// The seeded submission's title must show up in the emitted
			// DC — every parallel worker's tag is unique, so this also
			// proves isolation: a record with *our* tag exists.
			expect(body).toMatch(
				new RegExp(`<dc:title[^>]*>[^<]*Published article[^<]*${escapeRegex(tag)}[^<]*</dc:title>`),
			);

			// DC.Source (journal name) and DC.Language (locale code)
			// are always emitted for a journal-context OAI response.
			// DC.Source is emitted once per locale with xml:lang;
			// DC.Language has no attributes.
			expect(body).toMatch(/<dc:source[^>]*>[\s\S]*?Journal of Public Knowledge[\s\S]*?<\/dc:source>/);
			expect(body).toMatch(/<dc:language>en<\/dc:language>/);

			// Every record carries an identifier URL pointing to the
			// article's public view page. We don't hard-code the id here
			// (different workers seed different submissions) — just the
			// URL shape.
			expect(body).toMatch(/<dc:identifier[^>]*>[^<]*\/article\/view\/\d+[^<]*<\/dc:identifier>/);
		},
	);

	test(
		'GetRecord returns the specific record for a known identifier',
		async ({pkpApi, request}) => {
			const tag = uniqueTag(test.info(), 'dc-get');
			await pkpApi.createSubmission(submissionPublished({tag}));

			// First, fetch ListRecords and extract an <identifier> whose
			// record contains our tag — that way GetRecord is asserting
			// on the submission *this* test seeded rather than some
			// arbitrary record a parallel worker added.
			const listRes = await request.get(
				'/index.php/publicknowledge/oai?verb=ListRecords&metadataPrefix=oai_dc',
			);
			expect(listRes.status()).toBe(200);
			const listBody = await listRes.text();

			// Pull every <record>…</record> chunk and find the one whose
			// dc:title contains our tag. Extract its <identifier> child.
			const records = listBody.match(/<record>[\s\S]*?<\/record>/g) ?? [];
			const ours = records.find((r) => r.includes(tag));
			expect(ours, `no record contains tag ${tag}`).toBeDefined();
			const idMatch = /** @type {string} */ (ours).match(
				/<identifier>([^<]+)<\/identifier>/,
			);
			expect(idMatch, 'record missing <identifier>').not.toBeNull();
			const identifier = /** @type {RegExpMatchArray} */ (idMatch)[1];

			// Identifier shape: `oai:{repositoryId}:article/{articleId}`.
			expect(identifier).toMatch(/^oai:[^:]+:article\/\d+$/);

			// GetRecord with that identifier must come back 200 with a
			// single GetRecord wrapper and our title inside.
			const getRes = await request.get(
				'/index.php/publicknowledge/oai?verb=GetRecord&metadataPrefix=oai_dc&identifier=' +
					encodeURIComponent(identifier),
			);
			expect(getRes.status()).toBe(200);
			expect(getRes.headers()['content-type']).toContain('text/xml');

			const getBody = await getRes.text();
			expect(getBody).toContain('<GetRecord>');
			// The returned record's identifier must echo the requested one.
			expect(getBody).toContain(`<identifier>${identifier}</identifier>`);
			// The DC title round-trips the seeded tag. Match the tag on
			// the `<dc:title[^>]*>` opening form — OAIMetadataFormat_DC
			// emits an `xml:lang` attribute on every DC element.
			expect(getBody).toMatch(
				new RegExp(`<dc:title[^>]*>[^<]*Published article[^<]*${escapeRegex(tag)}[^<]*</dc:title>`),
			);
			// OAI errors come back as <error code="..."> — make sure we
			// don't see one (GetRecord against a missing identifier would
			// still return 200 but with an idDoesNotExist error body).
			expect(getBody).not.toMatch(/<error[^>]*>/);
		},
	);
});

/**
 * Build a tag scoped to this worker + test title so parallel workers
 * don't collide on the shared OAI records list. Mirrors the helper
 * used in article-dc-metadata.spec.js / publish-unpublish.spec.js.
 *
 * @param {import('@playwright/test').TestInfo} info
 * @param {string} suffix
 */
function uniqueTag(info, suffix) {
	const slug = info.title
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.slice(0, 16);
	return `t-w${info.parallelIndex}-${suffix}-${slug}`;
}

/**
 * Escape a string for inclusion in a RegExp. Tags include hyphens only
 * today, but playwright tags might grow to include `.` or `(` later, so
 * stay safe.
 *
 * @param {string} s
 */
function escapeRegex(s) {
	return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
