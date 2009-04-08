{**
 * view.tpl
 *
 * Copyright (c) 2000-2009 John Willinsky
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

<table width="100%">
	<tr>
		<td>{$announcement->getAnnouncementDescription()|nl2br}</td>
	</tr>
</table>

{include file="common/footer.tpl"}
