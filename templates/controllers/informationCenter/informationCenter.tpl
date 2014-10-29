{**
 * templates/controllers/informationCenter/informationCenter.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display information center's main modal.
 *}

<script type="text/javascript">
	// Attach the Information Center handler.
	$(function() {ldelim}
		$('#informationCenter').pkpHandler(
			'$.pkp.controllers.TabHandler', {ldelim}
				selected: {$selectedTabIndex|escape:"javascript"},
				notScrollable: true
			{rdelim}
		);
	{rdelim});
</script>

{if $lastEvent}
	<span class="pkp_controllers_informationCenter_itemLastEvent">{translate key="informationCenter.lastUpdated"}: {$lastEvent->getDateLogged()|date_format:$dateFormatShort}, {$lastEventUser->getFullName()|escape}</span>
	<br /><br />
{/if}

<div id="informationCenter" class="pkp_controllers_informationCenter pkp_controllers_tab">
	<ul>
		{if array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT), $userRoles) && $showMetadataLink}
			<li><a href="{url op="metadata" params=$linkParams}">{translate key="submission.informationCenter.metadata"}</a></li>
		{/if}
		<li><a href="{url op="viewNotes" params=$linkParams}">{translate key="submission.informationCenter.notes"}</a></li>
		{if !$removeHistoryTab}
			<li><a href="{url op="viewHistory" params=$linkParams}">{translate key="submission.informationCenter.history"}</a></li>
		{/if}
	</ul>
</div>
