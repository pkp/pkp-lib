{**
 * templates/manager/reviewForms/reviewFormElements.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display list of review form elements.
 *
 *}
{url|assign:reviewFormElementsUrl router=$smarty.const.ROUTE_COMPONENT component="grid.settings.reviewForms.ReviewFormElementsGridHandler" op="fetchGrid" reviewFormId=$reviewFormId}
{load_url_in_div id="ReviewFormElementsGridContainer" url=$reviewFormElementsUrl}
