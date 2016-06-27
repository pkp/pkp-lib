{**
 * templates/frontend/components/footer.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Common site frontend footer.
 *
 * @uses $isFullWidth bool Should this page be displayed without sidebars? This
 *       represents a page-level override, and doesn't indicate whether or not
 *       sidebars have been configured for thesite.
 *}

	</div><!-- pkp_structure_main -->

	{* Sidebars *}
	{if empty($isFullWidth)}
		{call_hook|assign:"leftSidebarCode" name="Templates::Common::LeftSidebar"}
		{if $leftSidebarCode}
			<div class="pkp_structure_sidebar left" role="complementary" aria-label="{translate|escape key="common.navigation.sidebar"}">
				{$leftSidebarCode}
			</div><!-- pkp_sidebar.left -->
		{/if}
	{/if}
</div><!-- pkp_structure_content -->

<div class="pkp_structure_footer_wrapper" role="contentinfo">

	<div class="pkp_structure_footer">

		{if $pageFooter}
			<div class="pkp_footer_content">
				{$pageFooter}
			</div>
		{/if}

		<div class="pkp_brand_footer" role="complementary" aria-label="{translate|escape key="about.aboutThisPublishingSystem"}">
			<a href="{url page="about" op="aboutThisPublishingSystem"}">
				<img alt="{translate key=$packageKey}" src="{$baseUrl}/{$brandImage}">
			</a>
			<a href="{$pkpLink}">
				<img alt="{translate key="common.publicKnowledgeProject"}" src="{$baseUrl}/lib/pkp/templates/images/pkp_brand.png">
			</a>
		</div>
	</div>

</div><!-- pkp_structure_footer_wrapper -->

</div><!-- pkp_structure_page -->

{call_hook name="Templates::Common::Footer::PageFooter"}
</body>
</html>
