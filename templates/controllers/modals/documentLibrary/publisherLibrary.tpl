{**
 * controllers/modals/documentLibrary/publisherLibrary.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Show a grid to manage files in the publisher library
 *
 * @uses $canEdit bool True if the current user can add/edit the files.
 *}

{* Help Link *}
{help file="settings" section="workflow-library" class="pkp_help_modal"}

{capture assign=libraryGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.library.LibraryFileAdminGridHandler" op="fetchGrid" canEdit=$canEdit escape=false}{/capture}
{load_url_in_div id="libraryGridDiv" url=$libraryGridUrl}
