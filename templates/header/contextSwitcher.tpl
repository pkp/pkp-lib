{**
 * templates/header/contextSwitcher.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Context switcher.
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#contextSwitcher').pkpHandler('$.pkp.pages.header.ContextSwitcherFormHandler',
			{ldelim}
				trackFormChanges: false
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="contextSwitcher" method="post" action="#">
	{fbvFormArea id="switcher" class="inline"}
		{fbvFormSection}
			{fbvElement type="select" id="contextSwitcherSelect" class="applyPlugin" from=$contextsNameAndUrl selected=$currentContextUrl translate=false}
		{/fbvFormSection}
	{/fbvFormArea}
</form>
