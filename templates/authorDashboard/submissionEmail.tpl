{**
 * templates/authorDashboard/submissionEmail.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Render a single submission email.
 *}
<blockquote>
	<div id="email-{$submissionEmail->getId()}">
		<table width="100%">
			<tr valign="top">
				<td>
					{translate key="email.subject}: {$submissionEmail->getSubject()|escape}<br />
					<span class="pkp_controllers_informationCenter_itemLastEvent">{$submissionEmail->getDateSent()|date_format:$datetimeFormatShort}</span>
				</td>
			</tr>
			<tr valign="top">
				{assign var="contents" value=$submissionEmail->getBody()}
				<td><br />
					{$submissionEmail->getBody()|strip_unsafe_html}
				</td>
			</tr>
		</table>
	</div>
</blockquote>
