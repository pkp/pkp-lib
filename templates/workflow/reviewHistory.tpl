{**
 * templates/workflow/reviewHistory.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Review history for a particular review assignment.
 *}

{if $reviewAssignment}
	<span class="pkp_controllers_informationCenter_itemLastEvent">{translate key="informationCenter.lastUpdated"}: {$reviewAssignment->getLastModified()|date_format:$dateFormatShort}</span>
	<br /><br />

	<div id="reviewAssignment-{$reviewAssignment->getId()}">
		<table width="100%" class="pkp_listing">
			<tr valign="top" class="heading">
				<td>{translate key="common.event"}</td>
				<td>{translate key="common.date"}</td>
			</tr>
			{if $reviewAssignment->getDateAssigned() != ''}
				<tr><td>{translate key="common.assigned"}</td><td>{$reviewAssignment->getDateAssigned()|date_format:$datetimeFormatShort}</td></tr>
			{/if}

			{if $reviewAssignment->getDateNotified() != ''}
				<tr><td>{translate key="common.notified"}</td><td>{$reviewAssignment->getDateNotified()|date_format:$datetimeFormatShort}</td></tr>
			{/if}

			{if $reviewAssignment->getDateReminded() != ''}
				<tr><td>{translate key="common.reminder"}</td><td>{$reviewAssignment->getDateReminded()|date_format:$datetimeFormatShort}</td></tr>
			{/if}

			{if $reviewAssignment->getDateConfirmed() != ''}
				<tr><td>{translate key="common.confirm"}</td><td>{$reviewAssignment->getDateConfirmed()|date_format:$datetimeFormatShort}</td></tr>
			{/if}

			{if $reviewAssignment->getDateCompleted() != ''}
				<tr><td>{translate key="common.completed"}</td><td>{$reviewAssignment->getDateCompleted()|date_format:$datetimeFormatShort}</td></tr>
			{/if}

			{if $reviewAssignment->getDateAcknowledged() != ''}
				<tr><td>{translate key="common.acknowledged"}</td><td>{$reviewAssignment->getDateAcknowledged()|date_format:$datetimeFormatShort}</td></tr>
			{/if}
		</table>
	</div>
	<br />
{/if}
