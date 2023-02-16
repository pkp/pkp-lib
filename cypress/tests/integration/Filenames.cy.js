/**
 * @file cypress/tests/integration/Filenames.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

 describe('Filename support for different character sets', function() {
	it('#6898 Tests submission file download name is correct', function() {
		var name = 'edição-£$L<->/4/ch 丹尼爾 a دانيال1d line \\n break.pdf';
		var encodedName = 'edi%C3%A7%C3%A3o-%C2%A3%24L%3C-%3E%2F4%2Fch+%E4%B8%B9%E5%B0%BC%E7%88%BE+a+%D8%AF%D8%A7%D9%86%D9%8A%D8%A7%D9%841d+line+%5Cn+break.pdf';
		var responseHeader = 'attachment;filename="' + encodedName + '";filename*=UTF-8\'\'' + encodedName;
		var stageId = Cypress.env('contextTitles').en === 'Public Knowledge Preprint Server' ? 5 : 1;
		var downloadUrl = 'index.php/publicknowledge/$$$call$$$/api/file/file-api/download-file?submissionFileId=1&submissionId=1&stageId=' + stageId;

		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/workflow/access/1')
			.then((thisWindow) => {

				// Grab the CSRF token
				cy.get('a:contains("Assign")').click();
				cy.get('input[name="csrfToken"]').then(($el) => {
					var csrfToken = $el.val();

					// Change the filename
					cy.request({
						method: 'PUT',
						url: 'index.php/publicknowledge/api/v1/submissions/1/files/1?stageId=' + stageId,
						headers: {
							'X-Csrf-Token': csrfToken
						},
						body: {
							name: {
								en: name,
								fr_CA: name
							}
						}
					}).then((response) => {

						// Check the download filename
						cy.request('GET', downloadUrl)
							.then((response) => {
								expect(response.headers).to.have.property('content-disposition', responseHeader);
							});
					});
				})

			});
	});
});