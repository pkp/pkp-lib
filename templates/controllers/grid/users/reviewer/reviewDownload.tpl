<html>
<head>
	<style>
		body {
			font-family: Arial;
			color: rgb(41, 41, 41);
		}

		h1, h2, h3, h4, h5, h6 {
			margin: 0;
			padding: 5px 0;
		}

		.section {
			margin-bottom: 15px;
		}

		.review-title {
			font-weight: bold;
			margin-bottom: 8px
		}

		.review-description {
			color: #777777;
			margin-bottom: 8px
		}
	</style>
</head>
<body>
<div class="section context-title">{$contextTitle|escape}</div>
<div class="section">
	<h2>{$cleanTitle}</h2>
</div>

<div class="section">
	<h3 style="font-weight: bold;">{$reviewerName}</h3>
</div>

{if $dateCompleted}
	<div class="section">
		<h4 style="font-weight: bold;">{translate key="common.completed"}: {$dateCompleted}</h4>
	</div>
{/if}

{if $recommendation}
	<div class="section">
		<h4 style="font-weight: bold;">{translate key="editor.submission.recommendation"}: {$recommendation}</h4>
	</div>
{/if}
{if $reviewFormResponses}
	{foreach from=$reviewFormElements item=reviewFormElement}
		{if $authorFriendly && !$reviewFormElement->getIncluded()}
			{continue}
		{/if}
		{assign var="elementId" value=$reviewFormElement->getId()}
		<div>
			<h4 class="review-title">{$reviewFormElement->getLocalizedQuestion()|strip_tags|escape}</h4>
		</div>
		{if $reviewFormElement->getLocalizedDescription()}
			<div class="review-description">{$reviewFormElement->getLocalizedDescription()|strip_tags|escape}</div>
		{/if}

		{assign var="value" value=$reviewFormResponses[$elementId]}

		{if in_array($reviewFormElement->getElementType(), [
		ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD,
		ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD,
		ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA
		])}
			<div class="section"><span>{$value|strip_tags|escape}</span></div>
		{elseif $reviewFormElement->getElementType() == ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES}
			{assign var="possibleResponses" value=$reviewFormElement->getLocalizedPossibleResponses()}
			{assign var="reviewFormCheckboxResponses" value=$reviewFormResponses[$elementId]}
			<div class="section">
				{foreach from=$possibleResponses key=key item=possibleResponse}
					<div>
						<input type="checkbox" {if in_array($key, $reviewFormCheckboxResponses)}checked="1"{/if}>
						<span>{$possibleResponse|escape}</span>
					</div>
				{/foreach}
			</div>
		{elseif $reviewFormElement->getElementType() == ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS}
			{assign var="possibleResponsesRadios" value=$reviewFormElement->getLocalizedPossibleResponses()}
			<div class="section">
				{foreach from=$possibleResponsesRadios key=key item=possibleResponseRadio}
					<div>
						<input type="radio" {if $reviewFormResponses[$elementId] == $key}checked="1"{/if}>
						<span>{$possibleResponseRadio|escape}</span>
					</div>
				{/foreach}
			</div>
		{elseif $reviewFormElement->getElementType() == ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX}
			{assign var="possibleResponsesDropdown" value=$reviewFormElement->getLocalizedPossibleResponses()}
			{assign var="dropdownResponse" value=$possibleResponsesDropdown[$reviewFormResponses[$elementId]]}
			<div class="section"><span>{$dropdownResponse|escape}</span></div>
		{/if}
	{/foreach}
{else}
	<h4 class="review-title" style="font-weight: bold;">{translate key="editor.review.reviewerComments"}</h4>
	<div class="review-description">
		<em style="font-weight: bold; color:#606060;">{translate key="submission.comments.canShareWithAuthor"}</em>
	</div>
	{if $submissionComments|count == 0}
		<div class="section"><span>{translate key="common.none"}</span></div>
	{else}
		{foreach from=$submissionComments item=comment}
			<div class="section"><span>{$comment->getComments()|strip_tags|escape}</span></div>
		{/foreach}
	{/if}

	{if !$authorFriendly}
		<div class="review-description">
			<em style="font-weight: bold; color:#606060;">{translate key="submission.comments.cannotShareWithAuthor"}</em>
		</div>
		{if $submissionCommentsPrivate|count === 0}
			<div class="section"><span>{translate key="common.none"}</span></div>
		{else}
			{foreach from=$submissionCommentsPrivate item=comment}
				<div class="section"><span>{$comment->getComments()|strip_tags|escape}</span></div>
			{/foreach}
		{/if}
	{/if}
{/if}

<div>
	<h4 class="review-title" style="font-weight: bold;">{translate key="reviewer.submission.reviewFiles"}</h4>
</div>

{foreach from=$submissionFiles item=file}
	<div class="section"><span>{$file->getLocalizedData('name')}</span></div>
{/foreach}
</body>
</html>
