{**
 * templates/frontend/pages/userRegisterSite.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Site registration.
 *}
{include file="frontend/components/header.tpl"}

<div class="page page_register_site">
	{include file="frontend/components/breadcrumbs.tpl" currentTitleKey="user.register"}

	<div class="description">
		{translate key="user.register.selectContext"}
	</div>

	{iterate from=contexts item=context}
		{if !$notFirstContext}
			<ul class="contexts">
			{assign var=notFirstContext value=1}
		{/if}
		<li><a href="{url context=$context->getPath() page="user" op="register"}">{$context->getLocalizedName()|escape}</a></li>
	{/iterate}

	{if $contexts->wasEmpty()}
		<div class="description">
			{translate key="user.register.noContexts"}
		</div>
	{else}
		</ul>
	{/if}

</div>

{include file="common/frontend/footer.tpl"}
