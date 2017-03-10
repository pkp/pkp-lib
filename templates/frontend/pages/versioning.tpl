{**
 * templates/frontend/pages/versioning.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Display the page to view the versioning policy.
 *
 * @uses $currentContext Journal|Press The current journal or press
 *}
{include file="frontend/components/header.tpl" pageTitle="about.versioning"}

<div class="page page_versioning_policy">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="about.versioning"}
	{$versioningPolicy}
</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
