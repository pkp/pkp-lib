{**
 * templates/authorDashboard/submissionEmails.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display submission emails to authors.
 *}

{if $submissionEmails && $submissionEmails->count()}

	<div class="pkp_submission_emails">
		<h3>{translate key="notification.notifications"}</h3>

		<ul>
			{foreach $submissionEmails as $submissionEmail}

				{capture assign=submissionEmailLinkId}submissionEmail-{$submissionEmail->id}{/capture}
				<script type="text/javascript">
					// Initialize JS handler.
					$(function() {ldelim}
						$('#{$submissionEmailLinkId|escape:"javascript"}').pkpHandler(
							'$.pkp.pages.authorDashboard.SubmissionEmailHandler',
							{ldelim}
								{* Parameters for parent LinkActionHandler *}
								actionRequest: '$.pkp.classes.linkAction.ModalRequest',
								actionRequestOptions: {ldelim}
									titleIcon: 'modal_information',
									title: {translate|json_encode key="notification.notifications"},
									modalHandler: '$.pkp.controllers.modal.AjaxModalHandler',
									url: {url|json_encode router=PKP\core\PKPApplication::ROUTE_PAGE page="authorDashboard" op="readSubmissionEmail" submissionId=$submission->getId() stageId=$stageId reviewRoundId=$reviewRoundId submissionEmailId=$submissionEmail->id escape=false}
								{rdelim}
							{rdelim}
						);
					{rdelim});
				</script>

				<li>
					<span class="message">
						<a href="#" id="{$submissionEmailLinkId|escape}">{$submissionEmail->subject|escape}</a>
					</span>
					<span class="date">
						{$submissionEmail->dateSent|date_format:$datetimeFormatShort}
					</span>
				</li>

			{/foreach}
		</ul>
	</div>
{/if}
