/**
 * @file cypress/support/api.js
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

class Api {

    constructor(baseUrl) {
        this.url = baseUrl;
    }

    contexts(id) {
        return this.url + '/contexts' + (id ? '/' + id : '');
    }

    submissions(id) {
        return this.url + '/submissions' + (id ? '/' + id : '');
    }

    submit(id) {
        return this.submissions(id) + '/submit';
    }

    publications(submissionId, publicationId) {
        return this.submissions(submissionId) + '/publications' + (publicationId ? '/' + publicationId : '');
    }

    contributors(submissionId, publicationId, contributorId) {
        return this.publications(submissionId, publicationId) + '/contributors' + (contributorId ? '/' + publicationId : '');
    }

    submissionFiles(submissionId, submissionFileId) {
        return this.submissions(submissionId) + '/files' + (submissionFileId ? '/' + submissionFileId : '');
    }
}

export default Api;