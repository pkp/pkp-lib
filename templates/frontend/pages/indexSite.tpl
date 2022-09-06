{**
 * templates/frontend/pages/indexSite.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Site index.
 *
 *}
{include file="frontend/components/header.tpl"}

<div class="page_index_site">

	{if $about}
		<div class="about_site">
			{$about}
		</div>
	{/if}

	<div class="servers">
		<h2>
			{translate key="context.contexts"}
		</h2>
		{if !$servers|@count}
			{translate key="site.noServers"}
		{else}
			<ul>
				{foreach from=$servers item="server"}
					{capture assign="url"}{url server=$server->getPath()}{/capture}
					{assign var="thumb" value=$server->getLocalizedData('serverThumbnail')}
					{assign var="description" value=$server->getLocalizedDescription()}
					<li{if $thumb} class="has_thumb"{/if}>
						{if $thumb}
							<div class="thumb">
								<a href="{$url}">
									<img src="{$serverFilesPath}{$server->getId()}/{$thumb.uploadName|escape:"url"}"{if $thumb.altText} alt="{$thumb.altText|escape|default:''}"{/if}>
								</a>
							</div>
						{/if}

						<div class="body">
							<h3>
								<a href="{$url}" rel="bookmark">
									{$server->getLocalizedName()}
								</a>
							</h3>
							{if $description}
								<div class="description">
									{$description}
								</div>
							{/if}
							<ul class="links">
								<li class="view">
									<a href="{$url}">
										{translate key="site.serverView"}
									</a>
								</li>
							</ul>
						</div>
					</li>
				{/foreach}
			</ul>
		{/if}
	</div>

</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
