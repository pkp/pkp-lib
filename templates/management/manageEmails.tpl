{**
 * templates/management/manageEmails.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief The settings page to manage emails in each context.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.manageEmails"}
	</h1>

	<list-panel
		class="manageEmails__listPanel"
		:items="currentMailables"
		:is-sidebar-visible="true"
	>
		<pkp-header slot="header">
			<h1>{translate key="manager.publication.emails"}</h1>
			<template slot="actions">
				<search
					search-label="{translate key="manager.mailables.search"}"
					:search-phrase="searchPhrase"
					@search-phrase-changed="(newSearch) => this.searchPhrase = newSearch"
				></search>
				<pkp-button @click="confirmResetAll" :is-warnable="true">
					{translate key="manager.emails.resetAll"}
				</pkp-button>
			</template>
		</pkp-header>
		<template v-slot:item-title="{ldelim}item{rdelim}">
			{{ item.name }}
		</template>
		<template v-slot:item-subtitle="{ldelim}item{rdelim}">
			{{ item.description }}
		</template>
		<template v-slot:item-actions="{ldelim}item{rdelim}">
			<pkp-button @click="openMailable(item)">
				<span aria-hidden="true">Edit</span>
				<span class="-screenReader">{{ __('common.editItem', {ldelim}name: item.name{rdelim}) }}</span>
			</pkp-button>
		</template>
		<template slot="sidebar">
			<pkp-header>
				<h2>
					<icon icon="filter" :inline="true"></icon>
					{translate key="common.filter"}
				</h2>
			</pkp-header>
			<pkp-filter
				v-for="(name, value) in groupFilters"
				:key="value"
				param="groupIds"
				:title="name"
				:value="value"
				:is-filter-active="isFilterActive('groupIds', value)"
				@add-filter="addFilter"
				@remove-filter="removeFilter"
			></pkp-filter>
			<div class="listPanel__block">
				<pkp-header>
					<h3>
						{translate key="manager.emails.sentFrom"}
					</h3>
				</pkp-header>
				<pkp-filter
					v-for="(name, value) in fromFilters"
					:key="value"
					param="fromRoleIds"
					:title="name"
					:value="parseInt(value, 10)"
					:is-filter-active="isFilterActive('fromRoleIds', parseInt(value))"
					@add-filter="addFilter"
					@remove-filter="removeFilter"
				></pkp-filter>
			</div>
			<div class="listPanel__block">
				<pkp-header>
					<h3>
						{translate key="manager.emails.sentTo"}
					</h3>
				</pkp-header>
				<pkp-filter
					v-for="(name, value) in toFilters"
					:key="value"
					param="toRoleIds"
					:title="name"
					:value="parseInt(value, 10)"
					:is-filter-active="isFilterActive('toRoleIds', parseInt(value))"
					@add-filter="addFilter"
					@remove-filter="removeFilter"
				></pkp-filter>
			</div>
		</template>
	</list-panel>
	<modal
		:close-label="__('common.close')"
		name="mailable"
		:title="currentMailable ? currentMailable.name : ''"
		@closed="mailableModalClosed"
	>
		<template v-if="currentMailable">
			<p>{{ currentMailable.description }}</p>
			<p>{translate key="manager.mailables.addTemplates"}</p>
			<list-panel
				:items="currentMailable.emailTemplates"
			>
				<pkp-header slot="header">
					<h3>{translate key="manager.mailables.templates"}</h3>
					<template slot="actions">
						<pkp-button @click="openTemplate()">{translate key="manager.emails.addEmail"}</pkp-button>
					</template>
				</pkp-header>
				<template v-slot:item-subtitle="{ldelim}item{rdelim}">
					{{ localize(item.name) }}
				</template>
				<template v-slot:item-actions="{ldelim}item{rdelim}">
					<badge v-if="item.key === currentMailable.emailTemplateKey">
						{translate key="common.default"}
					</badge>
					<pkp-button @click="openTemplate(item)">
						{translate key="common.edit"}
					</pkp-button>
					<pkp-button
						v-if="item.key === currentMailable.emailTemplateKey && item.id"
						:is-warnable="true"
						@click="confirmResetTemplate(item)"
					>
						{translate key="common.reset"}
					</pkp-button>
					<pkp-button
						v-else-if="item.id"
						:is-warnable="true"
						@click="confirmRemoveTemplate(item)"
					>
						{translate key="common.remove"}
					</pkp-button>
				</template>
			</list-panel>
		</template>
	</modal>
	<modal
		:close-label="__('common.close')"
		name="template"
		:title="currentTemplate ? '{translate key="manager.mailables.editTemplate"}' : '{translate key="manager.emails.addEmail"}'"
		@closed="templateModalClosed"
	>
		<pkp-form
			ref="templateForm"
			class="manageEmails__templateForm"
			v-bind="currentTemplateForm"
			@set="updateCurrentTemplateForm"
			@success="templateSaved"
		></pkp-form>
	</modal>
{/block}
