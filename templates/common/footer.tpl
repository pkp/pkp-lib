{**
 * templates/common/footer.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Common site footer.
 *}

</div><!-- pkp_structure_main -->
</div><!-- pkp_structure_body -->

<div class="pkp_structure_foot">

<div class="pkp_structure_subfoot">
	{if $footerCategories|@count > 0}{* include a section if there are footer link categories defined *}
		<div class="footerCategories pkp_helpers_clear">
			{foreach from=$footerCategories item=category name=loop}
				{assign var=links value=$category->getLinks()}
				<div class="size1of{$footerCategories|@count}">
					<h4><a href="{url page="links" op="link" path=$category->getPath()|escape}">{$category->getLocalizedTitle()|strip_unsafe_html}</a></h4>
					<ul>
						{foreach from=$links item=link}
							<li><a href="{$link->getLocalizedUrl()}">{$link->getLocalizedTitle()|strip_unsafe_html}</a></li>
						{/foreach}
						{if $links|@count < $maxLinks}
							{section name=padding start=$links|@count loop=$maxLinks step=1}
								<li class="pkp_helpers_invisible">&nbsp;</li>
							{/section}
						{/if}
					</ul>
				</div>
			{/foreach}
		</div><!-- footerCategories -->
	{/if}
	<div class="footerAboutPKPLink">
		<a href="{url page="about" op="aboutThisPublishingSystem"}"><img class="pkp_helpers_align_right" alt="{translate key=$packageKey}" src="{$baseUrl}/{$brandImage}"/></a>
		<a href="{$pkpLink}"><img class="pkp_helpers_align_right pkp_helpers_clear" alt="{translate key="common.publicKnowledgeProject"}" src="{$baseUrl}/lib/pkp/templates/images/pkp_brand.png"/></a>
	</div><!-- footerAboutPKPLink -->
	<div class="pageFooter">
		{if $pageFooter}{$pageFooter}{/if}
	</div><!-- pageFooter -->
</div><!-- pkp_structure_subfoot -->

</div><!-- pkp_structure_foot -->

{$additionalFooterData}
{call_hook name="Templates::Common::Footer::PageFooter"}
</body>
</html>
