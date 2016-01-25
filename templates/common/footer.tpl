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

<div class="pkp_structure_footer" role="contentinfo">
	<div class="pkp_brand_footer">
		<a href="{url page="about" op="aboutThisPublishingSystem"}">
			<img alt="{translate key=$packageKey}" src="{$baseUrl}/{$brandImage}">
		</a>
		<a href="{$pkpLink}">
			<img alt="{translate key="common.publicKnowledgeProject"}" src="{$baseUrl}/lib/pkp/templates/images/pkp_brand.png">
		</a>
	</div>
</div>

</body>
</html>
