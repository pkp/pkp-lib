{**
 * templates/frontend/pages/privacy.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display the page to view the privacy policy.
 *
 * @uses $currentContext Journal|Press The current journal or press
 *}
{include file="frontend/components/header.tpl" pageTitle="manager.setup.privacyStatement"}

<div class="page page_privacy">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="manager.setup.privacyStatement"}
	<h1>
		{translate key="manager.setup.privacyStatement"}
	</h1>
	{$privacyStatement}
</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
