{**
 * breadcrumbs.tpl
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Breadcrumbs
 *
 *}
<div id="breadcrumb">
	<a href="{url context=$homeContext page="index"}">{translate key="navigation.home"}</a> &gt;
	{foreach from=$pageHierarchy item=hierarchyLink}
		<a href="{$hierarchyLink[0]|escape}" class="hierarchyLink">{if not $hierarchyLink[2]}{translate key=$hierarchyLink[1]}{else}{$hierarchyLink[1]|escape}{/if}</a> &gt;
	{/foreach}
	{if !PKPRequest::isPost()}<a href="{$currentUrl|escape}" class="current">{else}<span class="current">{/if}{$pageCrumbTitleTranslated}{if !PKPRequest::isPost()}</a>{else}</span>{/if}
</div>
