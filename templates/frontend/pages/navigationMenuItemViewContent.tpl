{**
 * frontend/pages/navigationMenuItemViewContent.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display NavigationMenuItem content
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated=$title}

{include file="frontend/components/breadcrumbs.tpl" currentTitle=$title}

<div class="page">
	<h1 class="page_title">{$title|escape}</h1>
	{$content}
</div>

{include file="frontend/components/footer.tpl"}
