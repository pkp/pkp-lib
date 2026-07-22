{**
 * templates/reviewer/review/step3.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Show the step 3 review page
 *}

<div id="reviewerReviewStep3Form-{$uuid}">
	<reviewer-review-step3-form
		:submission-id="{$submissionId}"
	></reviewer-review-step3-form>
</div>

<script>
	// Revisiting this step mounts a second app over the first, so remove the old one.
	Object.keys(pkp.registry._instances)
		.filter(function(id) {
			return id.indexOf('reviewerReviewStep3Form-') === 0;
		})
		.forEach(function(id) {
			pkp.registry._instances[id].unmount();
			delete pkp.registry._instances[id];
		});

	pkp.registry.init('reviewerReviewStep3Form-{$uuid}', 'Page', {ldelim}{rdelim});
</script>
