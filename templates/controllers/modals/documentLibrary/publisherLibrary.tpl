{**
 * controllers/modals/documentLibrary/publisherLibrary.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Show a grid to manage files in the publisher library
 *
 * @uses $canEdit bool True if the current user can add/edit the files.
 *}

{capture assign=libraryGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.settings.library.LibraryFileAdminGridHandler" op="fetchGrid" canEdit=$canEdit escape=false}{/capture}
{load_url_in_div id="libraryGridDiv" url=$libraryGridUrl}
