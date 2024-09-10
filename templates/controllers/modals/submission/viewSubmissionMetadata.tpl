{**
 * lib/pkp/templates/controllers/modals/submission/viewSubmissionMetadata.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display submission metadata.
 *}

<div id="viewSubmissionMetadata" class="">
	<h3>{$publication->getLocalizedFullTitle(null, 'html')|strip_unsafe_html}</h3>
	{if $authors}<h4>{$authors|escape}</h4>{/if}
	<div class="abstract">
		{$publication->getLocalizedData('abstract')|strip_unsafe_html}
	</div>
	{if $additionalMetadata || $dataAvailability}
		<pkp-table>
		{foreach $additionalMetadata as $metadata}
			<pkp-table-row>
				{foreach $metadata as $metadataItem}
					{if $metadataItem@iteration % 2 != 0}
						<pkp-table-cell :is-row-header="true">{$metadataItem|escape}</pkp-table-cell>
					{else}
						<pkp-table-cell>{$metadataItem|escape}</pkp-table-cell>
					{/if}
				{/foreach}
			</pkp-table-row>
		{/foreach}
		{if $dataAvailability}
			<pkp-table-row>
				<pkp-table-cell :is-row-header="true">
					{translate key="submission.dataAvailability"}
				</pkp-table-cell>
				<pkp-table-cell>
					{$dataAvailability|strip_unsafe_html}
				</pkp-table-cell>
			</pkp-table-row>
		{/if}
		</pkp-table>
	{/if}
</div>
<script type="text/javascript">
	pkp.registry.init('viewSubmissionMetadata', 'Page', {$additionalMetadata|json_encode});
</script>
