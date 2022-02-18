/**
 * @file cypress/support/commands.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

import 'cypress-file-upload';
import 'cypress-wait-until';

Cypress.Commands.add('install', function() {
	cy.visit('/');

	// Administrator information
	cy.get('input[name=adminUsername]').type('admin', {delay: 0});
	cy.get('input[name=adminPassword]').type('admin', {delay: 0});
	cy.get('input[name=adminPassword2]').type('admin', {delay: 0});
	cy.get('input[name=adminEmail]').type('pkpadmin@mailinator.com', {delay: 0});

	// Database configuration
	cy.get('select[name=databaseDriver]').select(Cypress.env('DBTYPE'));
	cy.get('input[id^=databaseHost-]').clear().type(Cypress.env('DBHOST'), {delay: 0});
	cy.get('input[id^=databasePassword-]').clear().type(Cypress.env('DBPASSWORD'), {delay: 0});
	cy.get('input[id^=databaseUsername-]').clear().type(Cypress.env('DBUSERNAME'), {delay: 0});
	cy.get('input[id^=databaseName-]').clear().type(Cypress.env('DBNAME'), {delay: 0});

	// Files directory
	cy.get('input[id^=filesDir-]').clear().type(Cypress.env('FILESDIR'), {delay: 0});

	// Locale configuration
	cy.get('input[id=additionalLocales-en_US').check();
	cy.get('input[id=additionalLocales-fr_CA').check();

	// Complete the installation
	cy.get('button[id^=submitFormButton-]').click();
	cy.get('p:contains("has completed successfully.")');
});

Cypress.Commands.add('login', (username, password, context) => {
	context = context || 'index';
	password = password || (username + username);
	cy.visit('index.php/' + context + '/login/signIn', {
		method: 'POST',
		body: {username: username, password: password}
	});
});

Cypress.Commands.add('logout', function() {
	cy.visit('index.php/index/login/signOut');
});

Cypress.Commands.add('setLocale', locale => {
	cy.visit('index.php/index/user/setLocale/' + locale);
});

Cypress.Commands.add('resetPassword', (username,oldPassword,newPassword) => {
	oldPassword = oldPassword || (username + username);
	newPassword = newPassword || oldPassword;
	cy.get('input[name=oldPassword]').type(oldPassword, {delay: 0});
	cy.get('input[name=password]').type(newPassword, {delay: 0});
	cy.get('input[name=password2]').type(newPassword, {delay: 0});
	cy.get('button').contains('OK').click();
});

Cypress.Commands.add('register', data => {
	if (!('email' in data)) data.email = data.username + '@mailinator.com';
	if (!('password' in data)) data.password = data.username + data.username;
	if (!('password2' in data)) data.password2 = data.username + data.username;

	cy.visit('');
	cy.get('a').contains('Register').click();
	cy.get('input[id=givenName]').type(data.givenName, {delay: 0});
	cy.get('input[id=familyName]').type(data.familyName, {delay: 0});
	cy.get('input[id=affiliation]').type(data.affiliation, {delay: 0});
	cy.get('select[id=country]').select(data.country);
	cy.get('input[id=email]').type(data.email, {delay: 0});
	cy.get('input[id=username]').type(data.username, {delay: 0});
	cy.get('input[id=password]').type(data.password, {delay: 0});
	cy.get('input[id=password2]').type(data.password2, {delay: 0});

	cy.get('input[name=privacyConsent]').click();
	cy.get('button').contains('Register').click();
});

Cypress.Commands.add('createSubmission', (data, context) => {
	// Initialize some data defaults before starting
	if (data.type == 'editedVolume' && !('files' in data)) {
		data.files = [];
		// Edited volumes should default to a single file per chapter, named after it.
		data.chapters.forEach((chapter, index) => {
			data.files.push({
				'file': 'dummy.pdf',
				'fileName': chapter.title.substring(0, 40) + '.pdf',
				'fileTitle': chapter.title,
				'genre': 'Chapter Manuscript'
			});
			data.chapters[index].files = [chapter.title];
		});
	}
	if (!('files' in data)) data.files = [{
		'file': 'dummy.pdf',
		'fileName': data.title + '.pdf',
		'fileTitle': data.title,
		'genre': Cypress.env('defaultGenre')
	}];
	if (!('keywords' in data)) data.keywords = [];
	if (!('additionalAuthors' in data)) data.additionalAuthors = [];
	if ('series' in data) data.section = data.series; // OMP compatible
	// If 'additionalFiles' is specified, it's to be used to augment the default
	// set, rather than overriding it (as using 'files' would do). Add the arrays.
	if ('additionalFiles' in data) {
		data.files = data.files.concat(data.additionalFiles);
	}

	cy.get('a:contains("Make a New Submission"), div#myQueue a:contains("New Submission")').click();

	// === Submission Step 1 ===
	if ('section' in data) cy.get('select[id="sectionId"],select[id="seriesId"]').select(data.section);
	cy.get('input[id^="checklist-"]').click({multiple: true});
	switch (data.type) { // Only relevant to OMP
		case 'monograph':
			cy.get('input[id="isEditedVolume-0"]').click();
			break;
		case 'editedVolume':
			cy.get('input[id="isEditedVolume-1"]').click();
			break;
	}
	cy.get('input[id=privacyConsent]').click();
	if ('submitterRole' in data) {
		cy.get('input[name=userGroupId]').parent().contains(data.submitterRole).click();
	} else cy.get('input[id=userGroupId]').click();
	cy.get('button.submitFormButton').click();

	// === Submission Step 2 ===

	// OPS uses the galley grid
	if (Cypress.env('contextTitles').en_US == 'Public Knowledge Preprint Server') {
		data.files.forEach(file => {
			cy.get('a:contains("Add galley")').click();
			cy.wait(2000); // Avoid occasional failure due to form init taking time
			cy.get('div.pkp_modal_panel').then($modalDiv => {
				cy.wait(3000);
				if ($modalDiv.find('div.header:contains("Create New Galley")').length) {
					cy.get('div.pkp_modal_panel input[id^="label-"]').type('PDF', {delay: 0});
					cy.get('div.pkp_modal_panel button:contains("Save")').click();
					cy.wait(2000); // Avoid occasional failure due to form init taking time
				}
			});
			cy.get('select[id=genreId]').select(file.genre);
			cy.fixture(file.file, 'base64').then(fileContent => {
				cy.get('input[type=file]').attachFile(
					{fileContent, 'filePath': file.fileName, 'mimeType': 'application/pdf', 'encoding': 'base64'}
				);
			});
			cy.get('button').contains('Continue').click();
			cy.wait(2000);
			for (const field in file.metadata) {
				cy.get('input[id^="' + Cypress.$.escapeSelector(field) + '"]:visible,textarea[id^="' + Cypress.$.escapeSelector(field) + '"]').type(file.metadata[field], {delay: 0});
				cy.get('input[id^="language"').click({force: true}); // Close multilingual and datepicker pop-overs
			}
			cy.get('button').contains('Continue').click();
			cy.get('button').contains('Complete').click();
		});

	// Other applications use the submission files list panel
	} else {
		cy.get('button:contains("Add File")');

		// A callback function used to prevent Cypress from failing
		// when an uncaught exception occurs in the code. This is a
		// workaround for an exception that is thrown when a file's
		// genre is selected in the modal form. This exception happens
		// because the submission step 2 form handler attaches a
		// validator to the modal form.
		//
		// It should be possible to remove this workaround once the
		// submission process has been fully ported to Vue.
		const allowException = function(error, runnable) {
			return false;
		}
		cy.on('uncaught:exception', allowException);

		// File uploads
		const primaryFileGenres = ['Article Text', 'Book Manuscript', 'Chapter Manuscript'];
		data.files.forEach(file => {
			cy.fixture(file.file, 'base64').then(fileContent => {
				cy.get('input[type=file]').attachFile(
					{fileContent, 'filePath': file.fileName, 'mimeType': 'application/pdf', 'encoding': 'base64'}
				);
				var $row = cy.get('a:contains("' + file.fileName + '")').parents('.listPanel__item');
				if (primaryFileGenres.includes(file.genre)) {
					// For some reason this is locating two references to the button,
					// so just click the last one, which should be the most recently
					// uploaded file.
					$row.get('button:contains("' + file.genre + '")').last().click();
					$row.get('span:contains("' + file.genre + '")');
				} else {
					$row.get('button:contains("Other")').last().click();
					cy.get('#submission-files-container .modal label:contains("' + file.genre + '")').click();
					cy.get('#submission-files-container .modal button:contains("Save")').click();
				}
				// Make sure the genre selection is complete before moving to the
				// next file.
				$row.get('button:contains("What kind of file is this?")').should('not.exist');
			});
		});
	}

	// Save the ID to the data object
	cy.location('search')
		.then(search => {
			// this.submission.id = parseInt(search.split('=')[1], 10);
			data.id = parseInt(search.split('=')[1], 10);
		});

	cy.get('button').contains('Save and continue').click();

	// === Submission Step 3 ===
	// Metadata fields
	cy.get('input[id^="title-en_US-"').type(data.title, {delay: 0});
	cy.get('label').contains('Title').click(); // Close multilingual popover
	cy.get('textarea[id^="abstract-en_US"]').then(node => {
		cy.setTinyMceContent(node.attr('id'), data.abstract);
	});
	cy.get('ul[id^="en_US-keywords-"]').then(node => {
		data.keywords.forEach(keyword => {
			node.tagit('createTag', keyword);
		});
	});
	data.additionalAuthors.forEach(author => {
		if (!('role' in author)) author.role = 'Author';
		cy.get('a[id^="component-grid-users-author-authorgrid-addAuthor-button-"]').click();
		cy.wait(250);
		cy.get('input[id^="givenName-en_US-"]').type(author.givenName, {delay: 0});
		cy.get('input[id^="familyName-en_US-"]').type(author.familyName, {delay: 0});
		cy.get('select[id=country]').select(author.country);
		cy.get('input[id^="email"]').type(author.email, {delay: 0});
		if ('affiliation' in author) cy.get('input[id^="affiliation-en_US-"]').type(author.affiliation, {delay: 0});
		cy.get('label').contains(author.role).click();
		cy.get('form#editAuthor').find('button:contains("Save")').click();
		cy.get('div[id^="component-grid-users-author-authorgrid-"] span.label:contains("' + Cypress.$.escapeSelector(author.givenName + ' ' + author.familyName) + '")');
	});
	// Chapters (OMP only)
	if ('chapters' in data) data.chapters.forEach(chapter => {
		cy.waitJQuery();
		cy.get('a[id^="component-grid-users-chapter-chaptergrid-addChapter-button-"]:visible').click();
		cy.wait(2000); // Avoid occasional failure due to form init taking time

		// Contributors
		chapter.contributors.forEach(contributor => {
			cy.get('form[id="editChapterForm"] label:contains("' + Cypress.$.escapeSelector(contributor) + '")').click();
		});

		// Title/subtitle
		cy.get('form[id="editChapterForm"] input[id^="title-en_US-"]').type(chapter.title, {delay: 0});
		if ('subtitle' in chapter) {
			cy.get('form[id="editChapterForm"] input[id^="subtitle-en_US-"]').type(chapter.subtitle, {delay: 0});
		}
		cy.get('div.pkp_modal_panel div:contains("Add Chapter")').click(); // FIXME: Resolve focus problem on title field

		cy.flushNotifications();
		cy.get('form[id="editChapterForm"] button:contains("Save")').click();
		cy.get('div:contains("Your changes have been saved.")');
		cy.waitJQuery();

		// Files
		if ('files' in chapter) {
			cy.get('div[id="chaptersGridContainer"] a:contains("' + Cypress.$.escapeSelector(chapter.title) + '")').click();
			chapter.files.forEach(file => {
				cy.get('form[id="editChapterForm"] label:contains("' + Cypress.$.escapeSelector(chapter.title.substring(0, 40)) + '")').click();
			});
			cy.flushNotifications();
			cy.get('form[id="editChapterForm"] button:contains("Save")').click();
			cy.get('div:contains("Your changes have been saved.")');
		}

		cy.get('div[id^="component-grid-users-chapter-chaptergrid-"] a.pkp_linkaction_editChapter:contains("' + Cypress.$.escapeSelector(chapter.title) + '")');
	});
	cy.waitJQuery();
	cy.get('form[id=submitStep3Form]').find('button').contains('Save and continue').click();

	// === Submission Step 4 ===
	cy.waitJQuery();
	cy.get('form[id=submitStep4Form]').find('button').contains('Finish Submission').click();
	cy.get('button.pkpModalConfirmButton').click();
	cy.waitJQuery();
	cy.get('h2:contains("Submission complete")');
});

Cypress.Commands.add('findSubmissionAsEditor', (username, password, familyName, context) => {
	context = context || 'publicknowledge';
	cy.login(username, password, context);
	cy.get('button[id="active-button"]').click();
	cy.contains('View ' + familyName).click({force: true});
});

Cypress.Commands.add('sendToReview', (toStage, fromStage) => {
	if (!toStage) toStage = 'External';
	cy.get('*[id^=' + toStage.toLowerCase() + 'Review-button-]').click();
	if (fromStage == "Internal") {
		cy.get('form[id="promote"] button:contains("Next:")').click();
		cy.get('button:contains("Record Editorial Decision")').click();
	} else {
		cy.get('form[id="initiateReview"] button:contains("Send")').click();
	}
	cy.get('span.description:contains("Waiting for reviewers")');
});

Cypress.Commands.add('assignParticipant', (role, name, recommendOnly) => {
	var names = name.split(' ');
	cy.get('a[id^="component-grid-users-stageparticipant-stageparticipantgrid-requestAccount-button-"]:visible').click();
	cy.get('select[name=filterUserGroupId').select(role);
	cy.get('input[id^="namegrid-users-userselect-userselectgrid-"]').type(names[1], {delay: 0});
	cy.get('form[id="searchUserFilter-grid-users-userselect-userselectgrid"]').find('button[id^="submitFormButton-"]').click();
	cy.get('input[name="userId"]').click(); // Assume only one user results from the search.
	if (recommendOnly) cy.get('input[name="recommendOnly"]').click();
	cy.flushNotifications();
	cy.get('button').contains('OK').click();
	cy.waitJQuery();
});

Cypress.Commands.add('recordEditorialRecommendation', recommendation => {
	cy.get('a[id^="recommendation-button-"]').click();
	cy.get('select[id=recommendation]').select(recommendation);
	cy.get('button').contains('Record Editorial Recommendation').click();
	cy.get('div').contains('Recommendation:');
});

Cypress.Commands.add('assignReviewer', name => {
	cy.wait(2000); // FIXME: Occasional problems opening the grid
	cy.get('a[id^="component-grid-users-reviewer-reviewergrid-addReviewer-button-"]').click();
	cy.waitJQuery();
	cy.get('.listPanel--selectReviewer .pkpSearch__input', {timeout: 20000}).type(name, {delay: 0});
	cy.contains('Select ' + name).click();
	cy.waitJQuery();
	cy.get('button:contains("Add Reviewer")').click();
	cy.contains(name + ' was assigned to review');
	cy.waitJQuery();
});

Cypress.Commands.add('recordEditorialDecision', decision => {
	cy.get('ul.pkp_workflow_decisions:visible a:contains("' + Cypress.$.escapeSelector(decision) + '")', {timeout: 30000}).click();
	if (decision != 'Request Revisions' && decision != 'Decline Submission') {
		cy.get('button:contains("Next:")').click();
	}
	cy.get('button:contains("Record Editorial Decision")').click();
});

Cypress.Commands.add('performReview', (username, password, title, recommendation, comments, context) => {
	context = context || 'publicknowledge';
	comments = comments || 'Here are my review comments';
	cy.login(username, password, context);
	cy.get('a').contains('View ' + title).click({force: true});
	cy.get('input[id="privacyConsent"]').click();
	cy.get('button:contains("Accept Review, Continue to Step #2")').click();
	cy.get('button:contains("Continue to Step #3")').click();
	cy.wait(2000); // Give TinyMCE control time to load
	cy.get('textarea[id^="comments-"]').then(node => {
		cy.setTinyMceContent(node.attr('id'), comments);
	});
	if (recommendation) {
		cy.get('select#recommendation').select(recommendation);
	}
	cy.get('button:contains("Submit Review")').click();
	cy.get('button:contains("OK")').click();
	cy.get('h2:contains("Review Submitted")');
	cy.logout();
});

Cypress.Commands.add('createUser', user => {
	if (!('email' in user)) user.email = user.username + '@mailinator.com';
	if (!('password' in user)) user.password = user.username + user.username;
	if (!('password2' in user)) user.password2 = user.username + user.username;
	if (!('roles' in user)) user.roles = [];
	cy.get('div[id=userGridContainer] a:contains("Add User")').click();
	cy.wait(2000); // Avoid occasional glitches with given name field
	cy.get('input[id^="givenName-en_US"]').type(user.givenName, {delay: 0});
	cy.get('input[id^="familyName-en_US"]').type(user.familyName, {delay: 0});
	cy.get('input[name=email]').type(user.email, {delay: 0});
	cy.get('input[name=username]').type(user.username, {delay: 0});
	cy.get('input[name=password]').type(user.password, {delay: 0});
	cy.get('input[name=password2]').type(user.password2, {delay: 0});
	if (!user.mustChangePassword) {
		cy.get('input[name="mustChangePassword"]').click();
	}
	cy.get('select[name=country]').select(user.country);
	cy.contains('More User Details').click();
	cy.get('span:contains("Less User Details"):visible');
	cy.get('input[id^="affiliation-en_US"]').type(user.affiliation, {delay: 0});
	cy.get('form[id=userDetailsForm]').find('button[id^=submitFormButton]').click();
	user.roles.forEach(role => {
		cy.get('form[id=userRoleForm]').contains(role).click();
	});
	cy.server();
	cy.route({
		method: "POST",
		url: /update-user-roles$/
	}).as('finishUserCreation');
	cy.get('form[id=userRoleForm] button[id^=submitFormButton]').click();
	cy.waitJQuery();
	cy.wait('@finishUserCreation').then((interception) => {
		assert.isTrue(interception.response.body.status);
	});
});

Cypress.Commands.add('flushNotifications', function() {
	cy.window().then(win => {
		if (typeof pkp !== 'undefined' && typeof pkp.eventBus !== 'undefined') {
			pkp.eventBus.$emit('clear-all-notify');
		}
	});
});

Cypress.Commands.add('waitJQuery', function() {
	cy.waitUntil(() => cy.window().then(win => win.jQuery.active == 0));
});

Cypress.Commands.add('consoleLog', message => {
	cy.task('consoleLog', message);
});

Cypress.Commands.add('checkGraph', (totalAbstractViews, abstractViews, files, totalFileViews, fileViews) => {
	const today = new Date(),
		yesterday = (d => new Date(d.setDate(d.getDate()-1)) )(new Date),
		daysAgo90 = (d => new Date(d.setDate(d.getDate()-91)) )(new Date),
		daysAgo10 = (d => new Date(d.setDate(d.getDate()-10)) )(new Date),
		daysAgo50 = (d => new Date(d.setDate(d.getDate()-50)) )(new Date),
		d = daysAgo50;
	cy.get('button.pkpDateRange__button').click();
	cy.get('button:contains("Last 90 days")').click();
	cy.waitJQuery();
	cy.get('span:contains("' + daysAgo90.toLocaleDateString(['en-CA'], {timeZone: 'UTC'}) + ' — ' + yesterday.toLocaleDateString(['en-CA'], {timeZone: 'UTC'}) + '")');
	cy.get('button.pkpDateRange__button').click();
	cy.get('.pkpDateRange__input--start');
	cy.get('input.pkpDateRange__input--start').clear().type(daysAgo50.toLocaleDateString(['en-CA'], {timeZone: 'UTC'}));
	cy.get('input.pkpDateRange__input--end').clear().type(daysAgo10.toLocaleDateString(['en-CA'], {timeZone: 'UTC'}));
	cy.get('button:contains("Apply")').click();
	cy.get('.pkpDateRange__options').should('not.exist');
	cy.get('span:contains("' + daysAgo50.toLocaleDateString(['en-CA'], {timeZone: 'UTC'}) + ' — ' + daysAgo10.toLocaleDateString(['en-CA'], {timeZone: 'UTC'}) + '")');

	// Test that the hidden timeline table for screen readers is getting populated
	// with rows of content.
	Cypress.$('.pkpStats__graph table.-screenReader').removeClass('-screenReader');
	cy.get('div.pkpStats__graph  table caption:contains("' + totalAbstractViews + '")');
	cy.get('div.pkpStats__graph table thead tr th:contains("' + abstractViews + '")');
	while (d < daysAgo10) {
		var dateString = d.toLocaleDateString(['en-CA'], {timeZone: 'UTC', month: 'long', day: 'numeric', year: 'numeric'});
		// PHP quirk: The date format specifier %e front-pads single-digit days with a space and there
		// doesn't seem to be a standard way to avoid it. (Apparently %-e works but not documented.)
		dateString = dateString.replace(/^([^ ]*) ([^ ]), ([^ ]*)$/, '$1  $2, $3');
		cy.get('div.pkpStats__graph table tbody tr th:contains("' + dateString + '")');
		cy.log(d.toLocaleDateString(['en-CA'], {timeZone: 'UTC', month: 'long', day: 'numeric', year: 'numeric'}));
		d.setDate(d.getDate()+1);
	};
	cy.get('div.pkpStats__graphSelectors button:contains("Monthly")').click();
});

Cypress.Commands.add('checkTable', (articleDetails, articles, authors) => {
	cy.get('h2:contains("' + articleDetails + '")');
	cy.get('div:contains("2 of 2 ' + articles + '")');
	authors.forEach(author => {
		cy.get('.pkpStats__panel .pkpTable__cell:contains("' + author + '")');
	});
	cy.get('input.pkpSearch__input').type('shouldreturnzeromatches', {delay: 0});
	cy.get('div:contains("No ' + articles + ' were found with usage statistics matching these parameters.")');
	cy.get('div:contains("0 of 0 ' + articles + '")');
	cy.get('input.pkpSearch__input').clear().type(authors[0], {delay: 0});
	cy.get('.pkpStats__panel .pkpTable__cell:contains("' + authors[0] + '")');
	cy.get('div:contains("1 of 1 ' + articles + '")');
	cy.get('input.pkpSearch__input').clear();
});

Cypress.Commands.add('checkFilters', filters => {
	cy.get('button:contains("Filters")').click();
	filters.forEach(filter => {
		cy.get('div.pkpStats__filterSet button:contains("' + filter + '")');
	});
	cy.get('button:contains("Filters")').click();
});

