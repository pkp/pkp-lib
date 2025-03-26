{**
 * templates/controllers/modals/documentLibrary/documentLibrary.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Document library
 *}

{capture assign=submissionLibraryGridUrl}{url submissionId=$submission->getId() router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.files.submissionDocuments.SubmissionDocumentsFilesGridHandler" op="fetchGrid" escape=false}{/capture}
{load_url_in_div id="submissionLibraryGridContainer" url=$submissionLibraryGridUrl}
