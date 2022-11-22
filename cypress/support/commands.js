/**
 * @file cypress/support/commands.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

import Api from './api.js';
import 'cypress-file-upload';
import 'cypress-wait-until';

Cypress.Commands.add('setTinyMceContent', (tinyMceId, content) => {
	cy.window().then((win) => {
		cy.waitUntil(() => win.tinymce?.editors[tinyMceId]?.initialized, {timeout: 10000}).then(() => {
			const editor = win.tinymce.editors[tinyMceId];
			editor.setContent(content);
		});
	});
});

Cypress.Commands.add('getTinyMceContent', (tinyMceId, content) => {
	return cy.window().then((win) => {
		return cy.waitUntil(() => win.tinymce?.editors[tinyMceId]?.initialized, {timeout: 10000}).then(() => {
			const editor = win.tinymce.editors[tinyMceId];
			return editor.getContent();
		});
	});
});

// See https://stackoverflow.com/questions/58657895/is-there-a-reliable-way-to-have-cypress-exit-as-soon-as-a-test-fails/58660504#58660504
Cypress.Commands.add('abortEarly', (self) => {
	if (self.currentTest.state === 'failed') {
		return cy.task('shouldSkip', true);
	}
	cy.task('shouldSkip').then(value => {
		if (value) self.skip();
	});
});

Cypress.Commands.add('runQueueJobs', (queue, test, once) => {
	let command = 'php lib/pkp/tools/jobs.php run';

	if ( queue ) {
		command = command + ' --queue=' + queue;
	}

	if ( test || false ) {
		command = command + ' --test';
	}

	if ( once || false ) {
		command = command + ' --once';
	}

	cy.exec(command);
});

Cypress.Commands.add('purgeQueueJobs', (queue, all) => {
	let command = 'php lib/pkp/tools/jobs.php purge';

	if ( queue ) {
		command = command + ' --queue=' + queue;
	}

	if ( all || false ) {
		command = command + ' --all';
	}

	cy.exec(command);
});

Cypress.Commands.add('dispatchTestQueueJobs', () => {
	cy.exec('php lib/pkp/tools/jobs.php test');
});

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
	cy.clearCookies();
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

Cypress.Commands.add('findSubmissionAsEditor', (username, password, familyName, context) => {
	context = context || 'publicknowledge';
	cy.login(username, password, context);
	cy.get('button[id="active-button"]').click();
	cy.contains('View ' + familyName).click({force: true});
});

// Provides: @csrfToken
Cypress.Commands.add('getCsrfToken', () => {
	// Go to page where CSRF token is available
	cy.visit('/index.php/publicknowledge/user/profile');

	return cy.window().then((win) => {
		cy.wrap(win.pkp.currentUser.csrfToken).as('csrfToken');
	});
});

// Provides: @submissionId, @currentPublicationId, @currentPublicationApiUrl
Cypress.Commands.add('beginSubmissionWithApi', (api, data, csrfToken) => {
	return cy.request({
		url: api.submissions(),
		method: 'POST',
		headers: {
			'X-Csrf-Token': csrfToken
		},
		body: {
			sectionId: data.sectionId
		}
	}).then(xhr => {
		expect(xhr.status).to.eq(200);
	}).then(xhr => {
		cy.wrap(xhr.body.id).as('submissionId');
		cy.wrap(xhr.body.currentPublicationId).as('currentPublicationId');
		cy.wrap(xhr.body.publications[0]._href).as('currentPublicationApiUrl');
	});
});

// Requires: @currentPublicationApiUrl
Cypress.Commands.add('putMetadataWithApi', (data, csrfToken) => {
	const hasKeywords = typeof data.keywords !== 'undefined' && data.keywords.length;
	const hasWorkType = typeof data.workType !== 'undefined'; // OMP: Monograph or Edited Volume

	let body = {
		title: {en_US: data.title},
		abstract: {en_US: data.abstract},
	};
	if (hasKeywords) {
		body.keywords = {en_US: data.keywords}
	}
	if (hasWorkType) {
		body.workType = data.workType;
	}

	return cy.get('@currentPublicationApiUrl').then((currentPublicationApiUrl) => {
		cy.request({
			url: currentPublicationApiUrl,
			method: 'PUT',
			headers: {
				'X-Csrf-Token': csrfToken
			},
			body: body,
			timeout: 60000
		}).then(xhr => {
			expect(xhr.status).to.eq(200);
			expect(xhr.body.title.en_US).to.eq(data.title);
			expect(xhr.body.abstract.en_US).to.eq(data.abstract);
			if (hasKeywords) {
				expect(xhr.body.keywords.en_US.length).to.eq(data.keywords.length);
				data.keywords.forEach((keyword, i) => {
					expect(xhr.body.keywords.en_US[i]).to.eq(keyword);
				});
			}
		});
	});
});


Cypress.Commands.add('addSubmissionAuthorsWithApi', (api, data, csrfToken) => {
	if (typeof data.additionalAuthors === 'undefined' || !data.additionalAuthors.length) {
		return;
	}
	return cy.get('@currentPublicationId').then((currentPublicationId) => {
		cy.get('@submissionId').then((submissionId) => {
			data.additionalAuthors.forEach(author => {
				cy.request({
						url: api.contributors(submissionId, currentPublicationId),
						method: 'POST',
						headers: {
							'X-Csrf-Token': csrfToken
						},
						body: author
				}).then(xhr => {
					expect(xhr.status).to.eq(200);
				})
			});
			cy.request({
				url: api.publications(submissionId, currentPublicationId),
				method: 'GET'
			}).then(xhr => {
				data.additionalAuthors.forEach(author => {
					let publicationAuthor = xhr.body.authors.find(pAuthor => author.givenName.en_US === pAuthor.givenName.en_US);
					expect(publicationAuthor.familyName.en_US).to.equal(author.familyName.en_US);
					expect(publicationAuthor.affiliation.en_US).to.equal(author.affiliation.en_US);
					expect(publicationAuthor.email).to.equal(author.email);
					expect(publicationAuthor.country).to.equal(author.country);
				});
			});
		});
	});
});

Cypress.Commands.add('submitSubmissionWithApi', (id, csrfToken) => {
	const api = new Api(Cypress.env('baseUrl') + '/index.php/publicknowledge/api/v1');
	return cy.get('@submissionId').then((submissionId) => {
		cy.request({
			url: api.submit(submissionId),
			method: 'PUT',
			headers: {
				'X-Csrf-Token': csrfToken
			}
		})
		.then(xhr => {
			expect(xhr.status).to.eq(200);
		});
	});
});

/**
 * @var array authorNames The names of authors assigned to this submission
 */
Cypress.Commands.add('recordDecisionSendToReview', (decisionLabel, authorNames, filesToPromote) => {
	cy.get('h1').contains(decisionLabel).should('exist');
	cy.get('h2').contains('Notify Authors').should('exist');
	cy.waitForEmailTemplateToBeLoaded('Notify Authors');
	cy.checkComposerRecipients('Notify Authors', authorNames);
	cy.checkEmailTemplateVariables('#notifyAuthors-body-control');
	cy.get('.decision__footer button').contains('Continue').click();
	cy.selectPromotedFiles(filesToPromote);
	// Check for the correct confirmation message for different
	// decisions in OJS and OMP.
	if (decisionLabel === 'Send to External Review') {
		cy.recordDecision('has been sent to the external review stage');
	} else if (decisionLabel === 'Send to Internal Review') {
		cy.recordDecision('has been sent to the internal review stage');
	} else {
		cy.recordDecision('has been sent to the review stage');
	}
});

/**
 * @var array completedReviewerNames The names of reviewers who have completed a review assignment
 */
Cypress.Commands.add('recordDecisionAcceptSubmission', (authorNames, completedReviewerNames, filesToPromote) => {
	cy.get('h1').contains('Accept Submission').should('exist');
	cy.get('h2').contains('Notify Authors').should('exist');
	cy.waitForEmailTemplateToBeLoaded('Notify Authors');
	cy.checkComposerRecipients('Notify Authors', authorNames);
	cy.checkEmailTemplateVariables('#notifyAuthors-body-control');
	cy.get('.decision__footer button').contains('Continue').click();
	if (completedReviewerNames && completedReviewerNames.length) {
		cy.get('h2').contains('Notify Reviewers').should('exist');
		cy.waitForEmailTemplateToBeLoaded('Notify Reviewers');
		cy.checkComposerRecipients('Notify Reviewers', completedReviewerNames);
		cy.checkEmailRecipientVariable('#notifyReviewers-body-control', '{$recipientName}');
		cy.get('.decision__footer button').contains('Continue').click();
	}
	cy.selectPromotedFiles(filesToPromote);
	cy.recordDecision('has been accepted for publication and sent to the copyediting stage');
});

/**
 * @var array authorNames The names of authors assigned to this submission
 */
Cypress.Commands.add('recordDecisionSendToProduction', (authorNames, filesToPromote) => {
	cy.get('h1').contains('Send To Production').should('exist');
	cy.get('h2').contains('Notify Authors').should('exist');
	cy.waitForEmailTemplateToBeLoaded('Notify Authors');
	cy.checkComposerRecipients('Notify Authors', authorNames);
	cy.checkEmailTemplateVariables('#notifyAuthors-body-control');
	cy.get('.decision__footer button').contains('Continue').click();
	cy.selectPromotedFiles(filesToPromote);
	cy.recordDecision('was sent to the production stage');
});

/**
 * @var array completedReviewerNames The names of reviewers who have completed a review assignment
 */
Cypress.Commands.add('recordDecisionRevisions', (revisionLabel, authorNames, completedReviewerNames) => {
	cy.get('h1').contains(revisionLabel).should('exist');
	cy.get('h2').contains('Notify Authors').should('exist');
	cy.waitForEmailTemplateToBeLoaded('Notify Authors');
	cy.checkComposerRecipients('Notify Authors', authorNames);
	cy.checkEmailTemplateVariables('#notifyAuthors-body-control');
	cy.get('.decision__footer button').contains('Continue').click();
	if (completedReviewerNames && completedReviewerNames.length) {
		cy.get('h2').contains('Notify Reviewers').should('exist');
		cy.waitForEmailTemplateToBeLoaded('Notify Reviewers');
		cy.checkComposerRecipients('Notify Reviewers', completedReviewerNames);
		cy.checkEmailRecipientVariable('#notifyReviewers-body-control', '{$recipientName}');
	}
	cy.recordDecision('have been requested.');
});

Cypress.Commands.add('recordDecisionDecline', (authorNames) => {
	cy.get('h1').contains('Decline Submission').should('exist');
	cy.get('h2').contains('Notify Authors').should('exist');
	cy.waitForEmailTemplateToBeLoaded('Notify Authors');
	cy.checkComposerRecipients('Notify Authors', authorNames);
	cy.checkEmailTemplateVariables('#notifyAuthors-body-control');
	cy.recordDecision('has been declined and sent to the archives');
});

Cypress.Commands.add('recordDecisionRevertDecline', (authorNames) => {
	cy.get('h1').contains('Revert Decline').should('exist');
	cy.get('h2').contains('Notify Authors').should('exist');
	cy.waitForEmailTemplateToBeLoaded('Notify Authors');
	cy.checkComposerRecipients('Notify Authors', authorNames);
	cy.checkEmailTemplateVariables('#notifyAuthors-body-control');
	cy.recordDecision('now active in the submission stage');
});

/**
 * @param array decidingEditors The names of editors who can record a decision on this submission
 */
Cypress.Commands.add('recordRecommendation', (decisionLabel, decidingEditors) => {
	cy.get('h1').contains(decisionLabel).should('exist');
	cy.get('h2').contains('Notify Editors').should('exist');
	cy.waitForEmailTemplateToBeLoaded('Notify Editors');
	cy.checkComposerRecipients('Notify Editors', decidingEditors);
	cy.checkEmailTemplateVariables('#discussion-body-control');
	cy.recordDecision('Your recommendation has been recorded');
});

Cypress.Commands.add('decisionsExist', (buttonLabels) => {
	buttonLabels.forEach(buttonLabel => {
		cy.get('#editorialActions:contains("' + buttonLabel + '")').should('exist');
	});
});

Cypress.Commands.add('decisionsDoNotExist', (buttonLabels) => {
	buttonLabels.forEach(buttonLabel => {
		cy.get('#editorialActions:contains("' + buttonLabel + '")').should('not.exist');
	});
});

Cypress.Commands.add('clickDecision', (buttonLabel) => {
	cy.get('#editorialActions').contains(buttonLabel).click();
	cy.waitJQuery();
});

Cypress.Commands.add('selectPromotedFiles', (filenames) => {
	filenames.forEach(filename => {
		cy.get('label:contains("' + filename + '")').find('input').check();
	});
});

Cypress.Commands.add('recordDecision', (successMessage) => {
	cy.get('button:contains("Record Decision")').click();
	cy.get('#modals-container:contains("' + successMessage + '")').should('exist');
	cy.get('a.pkpButton').contains('View Submission').click();
});

Cypress.Commands.add('submissionIsDeclined', () => {
	cy.get('.pkpWorkflow__identificationStatus').contains('Declined').should('exist');
	cy.get('.pkp_workflow_last_decision:contains("Submission declined.")');
});

Cypress.Commands.add('isActiveStageTab', (stageName) => {
	cy.get('#stageTabs li.ui-state-active').contains(stageName);
});

/**
 * Check that all email template variables have been rendered
 * correctly in an email preview.
 *
 * @param string selector The HTML element selector for the text area where the value is stored
 */
Cypress.Commands.add('checkEmailTemplateVariables', (selector) => {
	cy.get(selector).should(($div) => {
		expect($div.get(0).textContent).not.to.include('{$');
	});
});

/**
 * Check that the {$recipientName} variable matches what is expected
 *
 * This is used when the composer is set to send separate emails
 * to each recipient, and {$recipientName} is kept in the message.
 *
 * @param string selector The HTML element selector for the text area where the value is stored
 */
Cypress.Commands.add('checkEmailRecipientVariable', (selector, value) => {
	cy.get(selector).should(($div) => {
		expect($div.get(0).textContent).to.include(value);
	});
});

/**
 * Wait for an email template to be loaded into an email composer
 *
 * @param string stepName The name of the step to wait for the email template to be loaded
 */
Cypress.Commands.add('waitForEmailTemplateToBeLoaded', (stepName) => {
	cy.get('h2:contains("' + stepName + '")')
		.parents('.pkpStep')
		.find('.composer__loadingTemplateMask')
		.should('not.exist');
});

/**
 * Check that the recipients in an email composer element match
 * those expected
 *
 * @param string stepName The name of the step to look for the email recipients
 * @param string[] recipientNames The expected names of recipients
 */
Cypress.Commands.add('checkComposerRecipients', (stepName, recipientNames) => {

	// Every expected name is there
	recipientNames.forEach(function(recipientName) {
		cy.get('h2:contains("' + stepName + '")')
			.parents('.pkpStep')
			.find('.composer__recipients .pkpAutosuggest__selection')
			.contains(recipientName);
	});

	// No unexpected names are there
	cy.get('h2:contains("' + stepName + '")')
		.parents('.pkpStep')
		.find('.composer__recipients .pkpAutosuggest__selection')
		.should('have.length', recipientNames.length);
});

Cypress.Commands.add('assignParticipant', (role, name, recommendOnly) => {
	var names = name.split(' ');
	cy.get('a[id^="component-grid-users-stageparticipant-stageparticipantgrid-requestAccount-button-"]:visible').click();
	cy.waitJQuery();
	cy.get('select[name=filterUserGroupId').select(role);
	cy.get('input[id^="namegrid-users-userselect-userselectgrid-"]').type(names[1], {delay: 0});
	cy.get('form[id="searchUserFilter-grid-users-userselect-userselectgrid"]').find('button[id^="submitFormButton-"]').click();
	cy.get('td:contains("' + name + '")').parents('tr').find('input[name="userId"]').check();
	if (recommendOnly) cy.get('input[name="recommendOnly"]').click();
	cy.flushNotifications();
	cy.get('button').contains('OK').click();
	cy.waitJQuery();
});

Cypress.Commands.add('clickStageParticipantButton', (participantName, buttonLabel) => {
	cy.get('[id^="component-grid-users-stageparticipant"] .has_extras:contains("' + participantName + '") .show_extras').click();
	cy.get('[id^="component-grid-users-stageparticipant"] .has_extras:contains("' + participantName + '")').closest('tr').next().find('a:contains("' + buttonLabel + '")').click();
});

Cypress.Commands.add('assignReviewer', name => {
	cy.wait(4000); // FIXME: Occasional problems opening the grid
	cy.get('a:contains("Add Reviewer")').click();
	cy.waitJQuery();
	cy.get('.listPanel--selectReviewer .pkpSearch__input', {timeout: 20000}).type(name, {delay: 0});
	cy.contains('Select ' + name).click();
	cy.waitJQuery();
	cy.get('button:contains("Add Reviewer")').click();
	cy.contains(name + ' was assigned to review');
	cy.waitJQuery();
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

Cypress.Commands.add('checkDoiConfig', doiTypes => {
	cy.get('a:contains("Distribution")').click();

	cy.get('button#dois-button').click();

	// DOI is or can be enabled
	cy.get('input[name="enableDois"]').check();
	cy.get('input[name="enableDois"]').should('be.checked');

	// Check all content
	doiTypes.forEach(doiType => {
		cy.get(`input[name="enabledDoiTypes"][value="${doiType}"]`).check();
	});

	// Declare DOI Prefix
	cy.get('input[name=doiPrefix]')
		.focus()
		.clear()
		.type('10.1234');

	// Select automatic DOI creation time
	cy.get('select[name="doiCreationTime"]').select('copyEditCreationTime');

	// Save
	cy.get('#doisSetup button')
		.contains('Save')
		.click();
	cy.get('#doisSetup [role="status"]').contains('Saved');
});

Cypress.Commands.add('assignDois', (itemId, itemType = 'submission') => {
	cy.get(`input[name="${itemType}[]"][value=${itemId}]`).check();

	// Select assign DOIs from bulk actions
	cy.get(`#${itemType}-doi-management button:contains("Bulk Actions")`).click({
		multiple: true
	});
	cy.get('button:contains("Assign DOIs")').click();

	// Confirm assignment
	cy.get(
		'div[data-modal="bulkActions"] button:contains("Assign DOIs")'
	).click();
	cy.get('.app__notifications').contains(
		'Items successfully assigned new DOIs',
		{timeout: 20000}
	);
});

Cypress.Commands.add('checkDoiAssignment', (selectorId) => {
	cy.get(`input#${selectorId}`).should($input => {
		const val = $input.val();
		expect(val).to.match(
			/10.1234\/[0-9abcdefghjkmnpqrstvwxyz]{6}[0-9]{2}/
			);
	});
});

Cypress.Commands.add('checkDoiFilterResults', (filterName, textToCheck, expectedCount, itemType = 'submission') => {
	if (filterName === 'Unpublished') {
		// 'Unpublished' is ambiguious so we must specify which 'Unpublished' button we mean
		cy.get(`#${itemType}-doi-management button:contains("${filterName}")`)
			.first()
			.click();
	} else {
		cy.get(
			`#${itemType}-doi-management button:contains("${filterName}")`
		).click();
	}

	// Wait for data to finish loading
	cy.get(`#${itemType}-doi-management .pkpSpinner`).should('not.exist');
	cy.get(`#${itemType}-doi-management .listPanel__items`).contains(textToCheck);

	if (expectedCount !== 0) {
		cy.get(`#${itemType}-doi-management ul.listPanel__itemsList`)
			.find('li')
			.its('length')
			.should('eq', expectedCount);
	} else {
		cy.get(`#${itemType}-doi-management .listPanel__empty`);
	}
});

Cypress.Commands.add('checkDoiMarkedStatus', (status, itemId, isValid, expectedStatus, itemType = 'submission') => {

	// Select the item
	cy.get(`input[name="${itemType}[]"][value=${itemId}]`).check()

	// Select mark [status] from bulk actions
	cy.get(`#${itemType}-doi-management button:contains("Bulk Actions")`).click({multiple: true});
	cy.get(`button:contains("Mark DOIs ${status}")`).click();
	cy.get(`div[data-modal="bulkActions"] button:contains("Mark DOIs ${status}")`).click();

	// Check success or failure message
	if (isValid) {
		cy.get('.app__notifications').contains(`Items successfully marked ${status}`, {matchCase: false, timeout:20000});
	} else {
		cy.get('div[data-modal="failedDoiActionModal"]').contains('Failed to mark the DOI', {timeout:20000});
	}

	cy.get(`#list-item-${itemType}-${itemId} .pkpBadge`).contains(expectedStatus);
	if (!isValid) {
		cy.get(`#${itemType}-doi-management .modal button:contains('Close')`).click();
	}
});

Cypress.Commands.add('uploadSubmissionFiles', (files, options) => {

	if (!files.length) {
		return;
	}

	options = {
		uploadUrl: /submissions\/\d+\/files$/,
		editUrl: /submissions\/\d+\/files\/\d+/,
		primaryFileGenres: ['Article Text', 'Book Manuscript', 'Chapter Manuscript'],
		...options
	};

	// Setup upload listeners
	cy.server();

	cy.route({
		method: "POST",
		url: options.uploadUrl,
	}).as('fileUploaded');

	cy.route({
		method: "POST",
		url: options.editUrl
	}).as('genreDefined');

	files.forEach(file => {
		cy.fixture(file.file, 'base64').then(fileContent => {

			// Upload the file
			cy.get('input[type=file]').attachFile(
				{
					fileContent,
					encoding: 'base64',
					filePath: file.fileName,
					mimeType: file.mimetype,
				}
			);
			cy.wait('@fileUploaded').its('status').should('eq', 200);

			// Set the file genre
			const $row = cy.get('a:contains("' + file.fileName + '")').parents('.listPanel__item');
			$row.contains('What kind of file is this?');
			if (options.primaryFileGenres.includes(file.genre)) {
				// For some reason this is locating two references to the button,
				// so just click the last one, which should be the most recently
				// uploaded file.
				$row.get('button:contains("' + file.genre + '")').last().click();
			} else {
				$row.get('button:contains("Other")').last().click();
				cy.get('.pkpFormField--options__optionLabel').contains(file.genre).click();
				cy.get('.modal button').contains('Save').click();
			}
			cy.wait('@genreDefined').its('status').should('eq', 200);

			// Check if the file genre is set
			//
			// The phrase "What kind of file is this?" will exist on the page because
			// it is there in the state data passed to the page component. So limit the
			// search to a list panel.
			//
			// Don't use $row because it references an element no longer in the DOM.
			cy.get('.listPanel:contains("What kind of file is this?")').should('not.exist');
			cy
				.get('.listPanel__item:contains("' + file.fileName + '")')
				.get('.pkpBadge:contains("' + file.genre + '")');
		});
	});
});
