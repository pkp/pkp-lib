{**
 * frontend/pages/navigationMenuItemViewContent.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display NavigationMenuItem content 
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated=$title}

<div class="page">
    {include file="frontend/components/breadcrumbs.tpl" currentTitle="$title"}
    {$content}
</div>

{include file="frontend/components/footer.tpl"}
