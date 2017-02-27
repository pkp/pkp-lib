{**
 * templates/dashboard/archives.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Dashboard archived submissions tab.
 *}

{* Help File *}
{help file="submissions.md" section="archives" class="pkp_help_tab"}

{assign var="uuid" value=""|uniqid|escape}
<div id="archived-submission-list-handler-{$uuid}">
    <script type="text/javascript">
        pkp.registry.init('archived-submission-list-handler-{$uuid}', 'SubmissionsListPanel', {$submissionListData});
    </script>
</div>
