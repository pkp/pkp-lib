{**
 * templates/submission/form/index.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Main template for the author's submission pages.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="submission.submit.title"}
	</h1>

	<script type="text/javascript">
		// Attach the JS file tab handler.
		$(function() {ldelim}
			$('#submitTabs').pkpHandler(
				'$.pkp.pages.submission.SubmissionTabHandler',
				{ldelim}
					submissionProgress: {$submissionProgress},
					selected: {$submissionProgress-1},
					cancelUrl: {url|json_encode page="submissions" escape=false},
					cancelConfirmText: {translate|json_encode key="submission.submit.cancelSubmission"}
				{rdelim}
			);
		{rdelim});
	</script>
	{if $currentContext->getData('disableSubmissions')}
		<notification>
			{translate key="manager.setup.disableSubmissions.notAccepting"}
		</notification>
	{else}
		<div id="submitTabs" class="pkp_controllers_tab">
			<ul>
				{foreach from=$steps key=step item=stepLocaleKey}
					<li><a name="step-{$step|escape}" href="{url op="step" path=$step submissionId=$submissionId sectionId=$sectionId}">{$step}. {translate key=$stepLocaleKey}</a></li>
				{/foreach}
			</ul>
		</div>
	{/if}
{/block}
