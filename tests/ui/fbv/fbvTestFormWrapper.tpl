{**
 * fbvTestFormWrapper.tpl
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display the fbv-coded test form in the context of the application.
 *
 *}
{strip}
{assign var="pageTitleTranslated" value="Form builder vocabulary: visual results"}
{include file="common/header.tpl"}
{/strip}

<button id="modalView" onclick="modal('{$baseUrl}/tools/fbvVisualResults.php?display=modal', 'nothing', '', new Array('ok'), '#modalView')">View results in modal</button>

{include file="fbvTestForm.tpl"}

{include file="common/footer.tpl"}