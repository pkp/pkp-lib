{**
 * templates/authorDashboard/top.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * The top of the author dashboard.
 *}
<div id="submissionHeader" class="pkp_page_header">
	<div class="pkp_helpers_align_right">
		<ul class="submission_actions pkp_helpers_flatlist">
			{if $uploadFileAction}
				<li id="{$uploadFileAction->getId()}">
					{include file="linkAction/linkAction.tpl" action=$uploadFileAction contextId="authorDashboard"}
				</li>
			{/if}
			<li id="{$submissionLibraryAction->getId()}">
				{include file="linkAction/linkAction.tpl" action=$submissionLibraryAction contextId="authorDashboard"}
			</li>
			<li id="{$viewMetadataAction->getId()}">
				{include file="linkAction/linkAction.tpl" action=$viewMetadataAction contextId="authorDashboard"}
			</li>
		</ul>
	</div>
	<div class="pkp_helpers_align_left"><span class="h2">{$pageTitleTranslated}</span></div>
	<div class="pkp_helpers_clear"></div>
		
	<p class="pkp_help">{translate key="submission.authorDashboard.description"}</p>
	<br />
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="authorDashboardNotification" requestOptions=$authorDashboardNotificationRequestOptions}
</div>
