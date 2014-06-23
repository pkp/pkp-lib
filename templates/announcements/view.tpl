{**
 * templates/announcements/view.tpl
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * View full announcement text.
 *
 *}
{strip}
{assign var="pageTitleTranslated" value=$announcementTitle}
{assign var="pageId" value="announcement.view"}
{include file="common/header.tpl"}
{/strip}

<table id="announcementDescription" width="100%">
	<tr>
		<td>{$announcement->getLocalizedDescription()|nl2br}</td>
	</tr>
</table>

{include file="common/footer.tpl"}
