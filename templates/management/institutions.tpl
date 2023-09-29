{**
 * templates/management/institutions.tpl
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Add and edit institutions
 *
 * @hook Template::Institutions []
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.setup.institutions"}
	</h1>

	<panel>
		<panel-section>
			<institutions-list-panel
				v-bind="components.institutions"
				@set="set"
			/>
		</panel-section>
		{call_hook name="Template::Institutions"}
	</panel>
{/block}
