{**
 * templates/controllers/informationCenter/signoffHistory.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Review history for a particular signoff.
 *}

{if $signoff}
	<div id="signoff-{$signoff->getId()}">
		<table width="100%" class="pkp_listing">
			<tr valign="top" class="heading">
				<td>{translate key="common.event"}</td>
				<td>{translate key="common.date"}</td>
			</tr>
			{if $signoff->getDateNotified() != ''}
				<tr><td>{translate key="common.notified"}</td><td>{$signoff->getDateNotified()|date_format:$datetimeFormatShort}</td></tr>
			{/if}

			{if $signoff->getDateUnderway() != ''}
				<tr><td>{translate key="submission.task.responseDueDate"}</td><td>{$signoff->getDateUnderway()|date_format:$datetimeFormatShort}</td></tr>
			{/if}

			{if $signoff->getDateCompleted() != ''}
				<tr><td>{translate key="common.completed"}</td><td>{$signoff->getDateCompleted()|date_format:$datetimeFormatShort}</td></tr>
			{/if}

			{if $signoff->getDateAcknowledged() != ''}
				<tr><td>{translate key="common.acknowledged"}</td><td>{$signoff->getDateAcknowledged()|date_format:$datetimeFormatShort}</td></tr>
			{/if}
		</table>
	</div>
<br />
{/if}
