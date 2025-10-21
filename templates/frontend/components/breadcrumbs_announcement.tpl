{**
 * templates/frontend/components/breadcrumbs_announcement.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display a breadcrumb nav item for announcements.
 *
 * @uses $currentTitle string The title to use for the current page.
 * @uses $currentTitleKey string Translation key for title of current page.
 *}

<nav class="cmp_breadcrumbs cmp_breadcrumbs_announcement">
	<ol>
		<li>
			<a href="{url page="index" router=\PKP\core\PKPApplication::ROUTE_PAGE}">
				{translate key="common.homepageNavigationLabel"}
			</a>
			<span class="separator" aria-hidden="true">{translate key="navigation.breadcrumbSeparator"}</span>
		</li>
		<li>
			<a href="{url page="announcement" router=\PKP\core\PKPApplication::ROUTE_PAGE}">
				{translate key="announcement.announcements"}
			</a>
			<span class="separator" aria-hidden="true">{translate key="navigation.breadcrumbSeparator"}</span>
		</li>
		<li class="current">
			<a href="{url}" aria-current="page">{$currentTitle|escape}</a>
		</li>
	</ol>
</nav>

