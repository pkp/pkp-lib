{**
 * templates/reviewer/review/reviewStepHeader.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Header for the submission review pages.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{$pageTitle}
	</h1>

	<script type="text/javascript">
		// Attach the JS file tab handler.
		$(function() {ldelim}
			$('#reviewTabs').pkpHandler(
				'$.pkp.pages.reviewer.ReviewerTabHandler',
				{ldelim}
					reviewStep: {$reviewStep|escape},
					selected: {$selected|escape}
				{rdelim}
			);
		{rdelim});
	</script>

	<div id="reviewTabs" class="pkp_controllers_tab">
		<ul>
			<li><a href="{url op="step" path=$submission->getId() step=1}">{translate key="reviewer.reviewSteps.request"}</a></li>
			<li><a href="{url op="step" path=$submission->getId() step=2}">{translate key="reviewer.reviewSteps.guidelines"}</a></li>
			<li><a href="{url op="step" path=$submission->getId() step=3}">{translate key="reviewer.reviewSteps.download"}</a></li>
			<li><a href="{url op="step" path=$submission->getId() step=4}">{translate key="reviewer.reviewSteps.completion"}</a></li>
		</ul>
	</div>
{/block}
