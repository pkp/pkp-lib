{**
 * templates/common/footer.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Common site footer.
 *}

</div><!-- pkp_structure_main -->
</div><!-- pkp_structure_content -->
</div><!-- pkp_structure_body -->

<div class="pkp_structure_foot">

{if $footerCategories|@count > 0}{* include a subfoot section if there are footer link categories defined *}
	<div class="pkp_structure_subfoot">
		<div class="pkp_structure_content">
			{foreach from=$footerCategories item=category name=loop}
				{assign var=links value=$category->getLinks()}
				<div class="unit size1of{$footerCategories|@count} {if $smarty.foreach.loop.last}lastUnit{/if}">
					<h4>{$category->getLocalizedTitle()}</h4>
					<ul>
						{foreach from=$links item=link}
							<li><a href="{$link->getUrl()}">{$link->getLocalizedTitle()}</a></li>
						{/foreach}
						{if $links|@count < $maxLinks}
							{section name=padding start=$links|@count loop=$maxLinks step=1}
								<li class="pkp_helpers_invisible">&nbsp;</li>
							{/section}
						{/if}
					</ul>
				</div>
			{/foreach}
		</div><!-- pkp_structure_content -->
	</div><!-- pkp_structure_subfoot -->
{/if}
<div class="pkp_structure_subfoot">
	<div class="pkp_structure_content">
		<a href="{url page="about" op="aboutThisPublishingSystem"}"><img class="pkp_helpers_align_right" alt="{translate key=$packageKey}" src="{$baseUrl}/{$brandImage}"/></a>
		<a href="{$pkpLink}"><img class="pkp_helpers_align_right pkp_helpers_clear" alt="{translate key="common.publicKnowledgeProject"}" src="{$baseUrl}/lib/pkp/templates/images/pkp_brand.png"/></a>
	</div><!-- pkp_structure_content -->
	<div class="pkp_structure_content">
		{if $pageFooter}{$pageFooter}{/if}
		{call_hook name="Templates::Common::Footer::PageFooter"}
	</div><!-- pkp_structure_content -->
</div><!-- pkp_structure_subfoot -->

</div><!-- pkp_structure_foot -->

</div><!-- pkp_structure_page -->

{$additionalFooterData}
</body>
</html>
