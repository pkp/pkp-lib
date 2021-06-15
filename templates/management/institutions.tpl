{**
 * templates/management/institutions.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Add and edit institutions
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.setup.institutions"}
	</h1>

	<tabs :track-history="true">
		<tab id="institutions" label="{translate key="manager.setup.institutions"}">
			<institutions-list-panel
				v-bind="components.institutions"
				@set="set"
			/>
		</tab>
		{call_hook name="Template::Institutions"}
	</tabs>
{/block}
