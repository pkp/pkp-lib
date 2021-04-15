/**
 * @file cypress/tests/integration/Filenames.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

 describe('Filename support for different character sets', function() {
	it('#6898 Tests submission file download name is correct', function() {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/workflow/access/1');
		cy.get('.ui-tabs-anchor').contains('Submission').eq(0).click();
		cy.get('#submissionFilesGridDiv .show_extras').eq(0).click();
		cy.get('[id^="component-grid-files-submission"]').contains('Edit').eq(0).click();
		cy.wait(3000);
		cy.get('[name="name[en_US]"').clear().type('edição-£$L<->/4/ch 丹尼爾 a دانيال1d line \\n break.pdf');
		cy.get('[name="name[fr_CA]"').clear().type('edição-£$L<->/4/ch 丹尼爾 a دانيال1d line \\n break.pdf');
		cy.get('[id^="submitFormButton"]').contains('Save').click();
		cy.get('.pkp_modal').should('not.exist');

		cy.request('GET', 'index.php/publicknowledge/$$$call$$$/api/file/file-api/download-file?submissionFileId=1&submissionId=1&stageId=1')
			.then((response) => {
				expect(response.headers).to.have.property('content-disposition', 'attachment; filename*=UTF-8\'\'"edi%C3%A7%C3%A3o-%C2%A3%24L%3C-%3E%2F4%2Fch+%E4%B8%B9%E5%B0%BC%E7%88%BE+a+%D8%AF%D8%A7%D9%86%D9%8A%D8%A7%D9%841d+line+%5Cn+break.pdf"');
			});
	});
});