{**
 * templates/dashboard/myQueue.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User related submissions tab.
 *}

{* Help File *}
{help file="submissions.md" section="my-queue" class="pkp_help_tab"}

<div class="pkp_content_panel">

	{assign var="uuid" value=""|uniqid|escape}
	<div id="my-submission-list-handler-{$uuid}">
		<script type="text/javascript">
			pkp.registry.init('my-submission-list-handler-{$uuid}', 'SubmissionsListPanel', {$submissionListData});
		</script>
	</div>
</div>
