{**
 * controllers/informationCenter/signoffNotes.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display the signoff notes form with an optional signoff selector.
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the containing div handler. (Consumes selectSignoff event.)
		$('#signoffNotesContainer').pkpHandler(
			'$.pkp.controllers.informationCenter.SignoffNotesHandler',
			{ldelim}
				signoffNotesFormUrl: '{url|escape:"javascript" router=$smarty.const.ROUTE_COMPONENT op="fetchNotesForm" submissionId=$submissionId stageId=$stageId escape=false}',
				signoffId: '{$signoffId}'
			{rdelim}
		);
	{rdelim});
</script>

<p>{translate key="submission.upload.response.description"}</p>

<div id="signoffNotesContainer">
	{if !$signoffId}
		<script type="text/javascript">
			$(function() {ldelim}
				// Attach the dropdown handler. (Triggers selectSignoff event.)
				$('#selectSignoff').pkpHandler(
					'$.pkp.controllers.form.DropdownHandler',
					{ldelim}
						getOptionsUrl: '{url|escape:"javascript" router=$smarty.const.ROUTE_COMPONENT op="getUserSignoffs" submissionId=$submissionId stageId=$stageId symbolic=$symbolic escape=false}',
						eventName: 'selectSignoff'
					{rdelim}
				);
			{rdelim});
		</script>
		<form class="pkp_form" id="selectSignoff">
			{fbvFormArea id="signoffSelectForm"}
				{fbvFormSection}
					{fbvElement type="select" class="noStyling" id="signoffSelect" from="submission.signoff.selectFile"|translate|to_array translate=false}
				{/fbvFormSection}
			{/fbvFormArea}
		</form>
	{/if}

	<div id="signoffNotesFormContainer">
	</div>
</div>
