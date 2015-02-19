{**
 * templates/authorDashboard/submissionEmails.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display submission emails to authors.
 *}

{if $submissionEmails && $submissionEmails->getCount()}
	<div class="pkp_submissionEmails">
		<div class="pkp_controllers_grid">
			<div class="grid_header_bar"><h3>{translate key="editor.review.personalMessageFromEditor"}</h3></div>
		</div>
		{assign var="submissionEmail" value=$submissionEmails->next()}
		{include file="authorDashboard/submissionEmail.tpl" submissionEmail=$submissionEmail}
		{if $submissionEmails->getCount() > 1} {* more than one, display the rest as a list *}

		<div class="pkp_controllers_grid">
			<div class="grid_header_bar"><h3>{translate key="submission.previousAuthorMessages"}</h3></div>
		</div>

			<table width="100%" class="pkp_listing">
				<tr valign="top" class="heading">
					<td>{translate key="common.date"}</td>
					<td>{translate key="common.subject"}</td>
				</tr>
				{iterate from=submissionEmails item=submissionEmail}
				{* Generate a unique ID for this email *}
				{capture assign=submissionEmailLinkId}submissionEmail-{$submissionEmail->getId()}{/capture}
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
										title: '{$submissionEmail->getSubject()|escape:"javascript"}',
										modalHandler: '$.pkp.controllers.modal.AjaxModalHandler',
										url: '{url|escape:"javascript" router=$smarty.const.ROUTE_PAGE page="authorDashboard" op="readSubmissionEmail" submissionId=$submission->getId() stageId=$stageId reviewRoundId=$reviewRoundId submissionEmailId=$submissionEmail->getId() escape=false}'
									{rdelim}
								{rdelim}
							);
						{rdelim});
					</script>
					<tr><td>{$submissionEmail->getDateSent()|date_format:$datetimeFormatShort}</td><td><div id="{$submissionEmailLinkId}">{null_link_action key=$submissionEmail->getSubject()|escape id="submissionEmail-"|concat:$submissionEmail->getId() translate=false}</div></td></tr>
				{/iterate}
			</table>
		{/if}
	</div>
{/if}
