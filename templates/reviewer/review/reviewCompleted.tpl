{**
 * templates/reviewer/review/reviewCompleted.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Show the review completed page.
 *
 *}

<h2>{translate key="reviewer.complete"}</h2>
<br />
<div class="separator"></div>

<p>{translate key="reviewer.complete.whatNext"}</p>

<div id="discussionManagerComplete-{$uuid}" class="mt-4">
    <discussion-manager-reviewer
        submission-id="{$submission->getId()|json_encode}"
        :submission-stage-id={$reviewAssignment->getStageId()|json_encode}
    ></discussion-manager-reviewer>
</div>

<script>
    pkp.registry.init('discussionManagerComplete-{$uuid}', 'Container');
</script>



