{**
 * templates/controllers/modals/documentLibrary/documentLibrary.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Document library
 *}

{help file="editorial-workflow" section="submission-library" class="pkp_help_modal"}

{capture assign=submissionLibraryGridUrl}{url submissionId=$submission->getId() router=$smarty.const.ROUTE_COMPONENT component="grid.files.submissionDocuments.SubmissionDocumentsFilesGridHandler" op="fetchGrid" escape=false}{/capture}
{load_url_in_div id="submissionLibraryGridContainer" url=$submissionLibraryGridUrl}
