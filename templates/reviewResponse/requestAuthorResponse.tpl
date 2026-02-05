{**
 * templates/reviewResponse/requestAuthorResponse.tpl
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Template for the RequestReviewRoundAuthorResponse page
 *}

{extends file="layouts/backend.tpl"}
{block name="page"}
	<request-review-round-author-response v-bind="pageInitConfig"></request-review-round-author-response>
{/block}
