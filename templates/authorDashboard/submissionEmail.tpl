{**
 * templates/authorDashboard/submissionEmail.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Render a single submission email.
 *}
<div class="pkp_submission_email">
	<h2>
		{$submissionEmail->getSubject()|escape}
	</h2>
	<div class="date">
		{$submissionEmail->getDateSent()|date_format:$datetimeFormatShort}
	</div>
	<div class="email_entry">
		{$submissionEmail->getBody()|strip_unsafe_html}
	</div>
</div>
