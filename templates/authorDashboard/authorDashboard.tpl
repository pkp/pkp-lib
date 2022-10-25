{**
 * templates/authorDashboard/authorDashboard.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display the author dashboard.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<div class="pkpWorkflow">
		<pkp-header class="pkpWorkflow__header">
			<h1 class="pkpWorkflow__identification">
				<badge
					v-if="submission.status === getConstant('STATUS_PUBLISHED')"
					class="pkpWorkflow__identificationStatus"
					:is-success="true"
				>
					{translate key="publication.status.published"}
				</badge>
				<badge
					v-else-if="submission.status === getConstant('STATUS_DECLINED')"
					class="pkpWorkflow__identificationStatus"
					:is-warnable="true"
				>
					{translate key="common.declined"}
				</badge>
				{include file="workflow/submissionIdentification.tpl"}
			</h1>
			<template slot="actions">
				<pkp-button
					v-if="uploadFileUrl"
					ref="uploadFileButton"
					@click="openFileUpload"
				>
					{translate key="common.upload.addFile"}
				</pkp-button>
				<pkp-button
					@click="openLibrary"
				>
					{translate key="editor.submissionLibrary"}
				</pkp-button>
			</template>
		</pkp-header>
		{include file="controllers/notification/inPlaceNotification.tpl" notificationId="authorDashboardNotification" requestOptions=$authorDashboardNotificationRequestOptions}
		<tabs :track-history="true">
			<tab id="publication" label="{translate key="submission.publication"}">
				<div class="pkpPublication" ref="publication" aria-live="polite">
					<pkp-header class="pkpPublication__header" :is-one-line="false">
						<span class="pkpPublication__status">
							<strong>{{ statusLabel }}</strong>
							<span v-if="workingPublication.status === getConstant('STATUS_PUBLISHED')" class="pkpPublication__statusPublished">{translate key="publication.status.published"}</span>
							<span v-else class="pkpPublication__statusUnpublished">{translate key="publication.status.unpublished"}</span>
						</span>
							<span v-if="publicationList.length > 1" class="pkpPublication__version">
								<strong tabindex="0">{{ versionLabel }}</strong> {{ workingPublication.version }}
								<dropdown
									class="pkpPublication__versions"
									label="{translate key="publication.version.all"}"
									:is-link="true"
									submenu-label="{translate key="common.submenu"}"
								>
									<ul>
										<li v-for="publication in publicationList" :key="publication.id">
											<button
												class="pkpDropdown__action"
												:disabled="publication.id === workingPublication.id"
												@click="setWorkingPublicationById(publication.id)"
											>
												{{ publication.version }} /
												<template v-if="publication.status === getConstant('STATUS_PUBLISHED')">{translate key="publication.status.published"}</template>
												<template v-else>{translate key="publication.status.unpublished"}</template>
											</button>
										</li>
									</ul>
								</dropdown>
							</span>
							<span class="pkpPublication__relation">
								<dropdown
									class="pkpWorkflow__relation"
									label="{translate key="publication.relation"}"
								>
									<pkp-form class="pkpWorkflow__relateForm" v-bind="components.{$smarty.const.FORM_ID_RELATION}" @set="set">
								</dropdown>
							</span>
							{if $canPublish}
								<template slot="actions">
									<pkp-button
										v-if="workingPublication.status === getConstant('STATUS_QUEUED')"
										ref="publish"
										@click="openPublish"
									>
										{translate key="publication.publish"}
									</pkp-button>
									<pkp-button
										v-else-if="workingPublication.status === getConstant('STATUS_PUBLISHED')"
										:is-warnable="true"
										@click="openUnpublish"
									>
										{translate key="publication.unpublish"}
									</pkp-button>
									<pkp-button
										v-if="canCreateNewVersion"
										ref="createVersion"
										@click="createVersion"
									>
										{translate key="publication.createVersion"}
									</pkp-button>
								</template>
							{/if}
					</pkp-header>
					<div
						v-if="workingPublication.status === getConstant('STATUS_PUBLISHED')"
						class="pkpPublication__versionPublished"
					>
						{translate key="publication.editDisabled"}
					</div>
					<tabs :is-side-tabs="true" :track-history="true" class="pkpPublication__tabs" :label="publicationTabsLabel">
						<tab id="titleAbstract" label="{translate key="publication.titleAbstract"}">
							<pkp-form v-bind="components.{$smarty.const.FORM_TITLE_ABSTRACT}" @set="set" />
						</tab>
						<tab id="contributors" label="{translate key="publication.contributors"}">
							<contributors-list-panel
								v-bind="components.contributors"
								class="pkpWorkflow__contributors"
								@set="set"
								:items="workingPublication.authors"
								:publication="workingPublication"
								:publication-api-url="submissionApiUrl + '/publications/' + workingPublication.id"
								@updated:publication="setWorkingPublication"
								@updated:contributors="setContributors"
							></contributors-list-panel>
						</tab>
						{if $metadataEnabled}
							<tab id="metadata" label="{translate key="submission.informationCenter.metadata"}">
								<pkp-form v-bind="components.{$smarty.const.FORM_METADATA}" @set="set" />
							</tab>
						{/if}
						<tab v-if="supportsReferences" id="citations" label="{translate key="submission.citations"}">
							<pkp-form v-bind="components.{$smarty.const.FORM_CITATIONS}" @set="set" />
						</tab>
						<tab id="galleys" label="{translate key="submission.layout.galleys"}">
							<div id="representations-grid" ref="representations">
								<spinner></spinner>
							</div>
						</tab>
						<tab id="queries" label="{translate key="submission.queries.production"}">
							<div id="queries-grid" ref="queries">
							{include file="controllers/tab/authorDashboard/production.tpl"}
							</div>
						</tab>
						{call_hook name="Template::Workflow::Publication"}
					</tabs>
					<span class="pkpPublication__mask" :class="publicationMaskClasses">
						<spinner></spinner>
					</span>
				</div>
			</tab>
			{call_hook name="Template::Workflow"}
		</tabs>
	</div>
{/block}
