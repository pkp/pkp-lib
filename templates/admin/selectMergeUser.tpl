{**
 * templates/admin/selectMergeUser.tpl
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * List users so the site administrator can choose users to merge.
 *}
{strip}
{assign var="pageTitle" value="admin.mergeUsers"}
{include file="common/header.tpl"}
{/strip}

<p>{if $gridParams.oldUserId}{translate key="admin.mergeUsers.into.description"}{else}{translate key="admin.mergeUsers.from.description"}{/if}</p>

{url|assign:mergeUsersGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.admin.mergeUsers.MergeUsersGridHandler" op="fetchGrid" params=$gridParams escape=false}
{load_url_in_div id="mergeUsersGridContainer" url=$mergeUsersGridUrl}

{include file="common/footer.tpl"}
