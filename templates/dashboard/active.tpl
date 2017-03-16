{**
 * templates/dashboard/active.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Dashboard active submissions tab.
 *}

{* Help File *}
{help file="submissions.md" section="active" class="pkp_help_tab"}

{assign var="uuid" value=""|uniqid|escape}
<div id="active-submission-list-handler-{$uuid}">
    <script type="text/javascript">
        pkp.registry.init('active-submission-list-handler-{$uuid}', 'SubmissionsListPanel', {$submissionListData});
    </script>
</div>
