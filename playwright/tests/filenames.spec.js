// @ts-check
const path = require('path');
const fs = require('fs');
const os = require('os');
const {test, expect} = require('../support/base-test.js');
const {SubmissionWizardPage} = require('../pages/SubmissionWizardPage.js');

/**
 * Filenames — row #17 in docs/e2e-playwright-migration.md.
 *
 * Ports the filename-support coverage from
 * lib/pkp/cypress/tests/integration/Filenames.cy.js.
 *
 * Feature: the submission file upload pipeline (FileUploader.vue →
 * dropzone → POST /submissions/{id}/files → SubmissionFile::name
 * setting[locale] storage → SubmissionFilesListItem rendering) must
 * round-trip filenames containing non-ASCII characters and shell-unsafe
 * punctuation without mangling them. Cypress's source checked the
 * download Content-Disposition header for UTF-8-encoded filename*=
 * attribute on a pre-existing submission file that it renamed via API;
 * here we exercise the complementary end of the same pipeline: upload
 * via the wizard UI and verify the list renders the exact name the user
 * uploaded.
 *
 * Scope deviations from the Cypress source:
 *   - Cypress depended on a pre-existing submission (id=1) seeded by a
 *     prior spec in the legacy serial chain. Rather than seed such a
 *     submission (scenario's SubmissionFilesProcessor is E1 territory —
 *     not yet landed), we stay in the wizard: open a fresh submission
 *     via `pkpApi.createJournal` → wizard UI, upload a file with a
 *     tricky name, and assert the name renders back in the
 *     listPanel__item DOM. This exercises the upload half of the
 *     pipeline and the rendering half — what Cypress's rename-via-API
 *     path skipped.
 *   - The download-header Content-Disposition assertion is deferred
 *     until E1 lands. Once FilesProcessor exists, add a follow-up test
 *     here that seeds a submission with a file whose name contains
 *     unicode + punctuation, then fetches the download URL and asserts
 *     on the `filename*=UTF-8''…` attribute value.
 *   - French multilingual filename (Cypress seeded {en: name, fr_CA: name})
 *     dropped; the wizard UI only exposes a single localized name at
 *     upload time (filenameLocale = primaryLocale), so the multilingual
 *     round-trip is a different capability that lives on the
 *     SubmissionFilesEditModal (row to be named later).
 *
 * OJS / OMP / OPS share this upload component, so the spec lives under
 * lib/pkp/playwright/tests/ — file-upload behaviour is identical across
 * apps modulo genre labels, and Article Text is the default
 * primaryGenre in OJS only. The spec substitutes genre selection with
 * whatever primary genre button the wizard exposes first.
 */

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `fn-w${workerIndex}-${suffix}`;
}

/**
 * Prepare a temporary copy of the fixture PDF under a trickier filename.
 * Playwright's `setInputFiles` resolves file names from the path on
 * disk, so to upload a file named `test édition & £ 丹 (1).pdf` the path
 * on disk must carry that name. We copy the baseline `dummy.pdf`
 * fixture into the OS temp dir with the target name and return the
 * absolute path; the caller is responsible for cleaning it up.
 *
 * @param {string} displayName  The filename the user will "upload as".
 * @returns {string} Absolute path to the staged fixture.
 */
function stageFixture(displayName) {
	const src = path.resolve(
		__dirname,
		'../fixtures/files/dummy.pdf',
	);
	// Playwright-with-dropzone quirk: the virtual file the browser sees
	// comes from the absolute path, and Node's fs.copyFileSync preserves
	// the filename component exactly. Use a unique subdir so parallel
	// workers don't collide on the same display name.
	const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pkp-filenames-'));
	const dst = path.join(dir, displayName);
	fs.copyFileSync(src, dst);
	return dst;
}

test.describe('Filenames — upload sanitization round-trip', () => {
	test(
		'uploaded filename with non-ASCII + punctuation round-trips into the file list',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();

			// E0 scratch journal. dbarnes = manager so we can run the
			// wizard as an authenticated user without seeding a distinct
			// submitter. Journal-config isn't mutated by this test — any
			// filename handling defect is app-level, not config-level —
			// but we use a scratch journal anyway so parallel workers
			// don't compete over the publicknowledge wizard's submission
			// id sequence.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const ctx = await browser.newContext({baseURL});
			try {
				const page = await ctx.newPage();

				// Log in via the scratch-journal login form. Storage-state
				// baseline is scoped to publicknowledge.
				await page.goto(`/index.php/${context.path}/en/login`);
				await page.locator('input#username').fill('dbarnes');
				await page
					.locator('input#password')
					.fill('dbarnesdbarnes');
				await page.locator('form#login button').click();
				await page.waitForURL(
					(url) => !url.pathname.includes('/login'),
					{timeout: 15_000},
				);

				// Start a fresh wizard session.
				const wizard = new SubmissionWizardPage(page, context.path);
				await wizard.goto();
				await wizard.start({title: `Filenames ${tag}`});

				// On the Upload Files step. Compose a filename that
				// exercises several sanitization surfaces in one go:
				// non-ASCII (Latin-1 + CJK + Arabic), punctuation often
				// eaten by naive path handling (&, £), spaces, and
				// parentheses. Avoid `/ \ : * ? " < > |` — those are
				// filesystem-illegal on macOS / Windows and can't be
				// staged on disk for setInputFiles. The pipeline's
				// handling of those is validated separately by backend
				// unit tests.
				const uploadName =
					`Édition £ 丹尼爾 & دانيال (${tag}).pdf`;
				const stagedPath = stageFixture(uploadName);

				// Dropzone appends its hidden <input type="file"> to the
				// element with id = id + '-uploader' (FileUploader.vue's
				// hiddenInputContainer). The wizard's file-upload list
				// panel mounts the FileUploader with
				// id="submissionWizardFiles". The input is visually
				// hidden but still focusable — setInputFiles works on it
				// regardless of display state.
				const fileInput = page.locator('input[type="file"]').first();
				await expect(fileInput).toBeAttached({timeout: 15_000});

				// Race setInputFiles with the upload response so we know
				// the server accepted the file before asserting on the
				// UI. The endpoint is
				// /api/v1/submissions/{id}/files (POST).
				const [uploadRes] = await Promise.all([
					page.waitForResponse(
						(res) =>
							res.request().method() === 'POST' &&
							/\/api\/v1\/submissions\/\d+\/files$/.test(
								res.url(),
							) &&
							res.ok(),
						{timeout: 20_000},
					),
					fileInput.setInputFiles(stagedPath),
				]);
				const uploadedBody = await uploadRes.json();
				// The API echoes the stored name back as
				// `name: {<primaryLocale>: <string>}`. That's the
				// authoritative "what did the server record" signal.
				// Assert on it directly before touching the DOM — if the
				// server mangled the name, the UI assertion would only
				// confirm it rendered the mangled name.
				expect(
					uploadedBody?.name?.en ?? uploadedBody?.name,
				).toBe(uploadName);

				// The uploaded file now renders as a listPanel item. The
				// wizard still prompts "What kind of file is this?" until
				// a genre is picked (SubmissionFilesListItem.vue shows
				// genre-picker buttons when item.genreId is unset), so
				// the list item DOM is present with the filename before
				// any genre click. Scope the assertion to the list panel
				// so the "uploaded file name" string elsewhere on the
				// page (there shouldn't be any, but defensive) can't
				// match.
				const listItem = page
					.locator('.listPanel__item--submissionFile')
					.filter({hasText: uploadName});
				await expect(listItem).toBeVisible({timeout: 15_000});

				// Complete the genre assignment — any primary genre will
				// do. The wizard exposes Article Text (OJS) / Book
				// Manuscript (OMP) / Chapter Manuscript (OMP) as primary
				// on the journal + publicknowledge defaults; on a fresh
				// scratch journal, OJS's default_genres sql seeds
				// ARTICLE, STYLE, BINARY, OTHER — ARTICLE (Article Text)
				// is the default primary. Click whichever primary button
				// renders first to exercise the full save path.
				const genreButton = listItem
					.locator(
						'.listPanel--submissionFiles__setGenreButton',
					)
					.first();
				await expect(genreButton).toBeVisible();
				// The PUT that stores the genre echoes the file record
				// again — wait on it so the subsequent Badge assertion
				// doesn't race the XHR.
				await Promise.all([
					page.waitForResponse(
						(res) =>
							res.request().method() === 'POST' &&
							/\/api\/v1\/submissions\/\d+\/files\/\d+/.test(
								res.url(),
							) &&
							res.ok(),
						{timeout: 15_000},
					),
					genreButton.click(),
				]);

				// After save the file item re-renders with its genre
				// badge and the "What kind of file is this?" prompt is
				// gone — and the filename is STILL the one we uploaded.
				// This is the load-bearing assertion: a naive
				// re-fetch-then-render flow could drop non-ASCII
				// characters in JSON → Vue round-trip.
				await expect(
					listItem.locator(
						'.listPanel--submissionFiles__itemGenre',
					),
				).toBeVisible();
				await expect(listItem).toContainText(uploadName);
				await expect(
					listItem.getByText('What kind of file is this?'),
				).toHaveCount(0);

				// Download round-trip: hit the legacy
				// `$$$call$$$/api/file/file-api/download-file`
				// endpoint (mirrors Cypress's `Filenames.cy.js#15`)
				// and assert the `Content-Disposition` header
				// round-trips the non-ASCII filename via
				// `filename*=UTF-8''…`. The encoded form is what
				// browsers use to render the download dialog with
				// the correct name.
				const fileId = uploadedBody?.id;
				const submissionId = uploadedBody?.submissionId;
				expect(
					fileId && submissionId,
					'upload response carries file + submission ids',
				).toBeTruthy();
				// stageId=1 (submission stage) for OJS — same as the
				// Cypress source. Per
				// `lib/pkp/cypress/tests/integration/Filenames.cy.js`,
				// the URL pattern is fixed.
				const downloadUrl =
					`/index.php/${context.path}/$$$call$$$/api/file/file-api/download-file` +
					`?submissionFileId=${fileId}&submissionId=${submissionId}&stageId=1`;
				const dlResp = await page.request.get(downloadUrl);
				expect(
					dlResp.ok(),
					`download GET ${downloadUrl} → ${dlResp.status()}`,
				).toBeTruthy();
				const cd = dlResp.headers()['content-disposition'] || '';
				// The header carries an RFC-5987 `filename*=UTF-8''…`
				// segment whose value is the percent-encoded name.
				// Assert (a) the segment exists and (b) decoding it
				// yields the original uploaded filename.
				const m = cd.match(/filename\*=UTF-8''([^;]+)/i);
				expect(
					m,
					`Content-Disposition has filename*=UTF-8''…: ${cd}`,
				).toBeTruthy();
				// PKP's encoder uses form-style URL encoding
				// (`+` for spaces) rather than strict RFC 3986
				// (`%20`). decodeURIComponent treats `+` as a literal,
				// so substitute spaces back in before decoding.
				const decoded = decodeURIComponent(m[1].replace(/\+/g, ' '));
				expect(decoded).toBe(uploadName);
			} finally {
				await ctx.close();
			}
		},
	);
});
