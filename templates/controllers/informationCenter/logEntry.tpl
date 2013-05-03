{**
 * templates/controllers/informationCenter/logEntry.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a single log entry element.
 *
 *}

<tr valign="top">
	<td>{$logEntry->getDateLogged()|date_format:$dateFormatShort}</td>
	<td>{$logEntry->getUserFullName()|escape}</td>
	<td>{translate key=$logEntry->getMessage() params=$logEntry->getParams()}</td>
</tr>
