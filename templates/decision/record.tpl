{**
 * templates/management/workflow.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief The workflow settings page.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<div class="app__page--decision">
		<h1 class="app__pageHeading">
			<template v-if="workflow.length > 1">
				{translate key="semicolon" label=$decisionType->getLabel()}
				{{ currentStep.name }}
			</template>
			<template v-else>
				{$decisionType->getLabel()}
			</template>
		</h1>
		<p class="app__pageDescription">
			{$decisionType->getDescription()}
		</p>

		<notification
			v-for="(error, stepId) in errors"
			:key="stepId"
			type="warning"
			class="decision__error"
			role="alert"
		>
			{{ error }}
			<button class="-linkButton" @click="openStep(stepId)">
				{translate key="common.viewError"}
			</button>
		</notification>

		<steps
			v-if="workflow.length"
			:current="currentStep.id"
			:initialized-steps="initializedSteps"
			label="{translate key="editor.decision.completeSteps"}"
			progress-label="{$current}/{$total} steps"
			show-steps-label="{translate key="common.showAllSteps"}"
			@step:open="openStep"
		>
			<step
				v-for="step in workflow"
				:key="step.id"
				:id="step.id"
				:label="step.name"
			>
				<template v-if="step.type === 'form'">
					<panel>
						<panel-section>
							<template slot="header">
								<h2>{{ step.name }}</h2>
								<p>{{ step.description }}</p>
							</template>
							<pkp-form v-bind="step.form" @set="updateStep"></pkp-form>
						</panel-section>
					</panel>
				</template>
				<template v-else-if="step.type === 'email'">
					<panel v-if="skippedSteps.includes(step.id)">
						<panel-section>
							<template slot="header">
								<h2>{{ step.name }}</h2>
								<p>{translate key="editor.decision.emailSkipped"}</p>
							</template>
						</panel-section>
					</panel>
					{capture assign="attachedFilesLabel"}{translate key="common.attachedFiles"}{/capture}
					{capture assign="bccLabel"}{translate key="email.bcc"}{/capture}
					{capture assign="ccLabel"}{translate key="email.cc"}{/capture}
					{capture assign="subjectLabel"}{translate key="email.subject"}{/capture}
					{capture assign="switchTolabel"}{translate key="common.switchTo"}{/capture}
					{capture assign="toLabel"}{translate key="email.to"}{/capture}
					<composer
						v-else
						v-bind="step"
						add-c-c-label="{translate key="common.addCCBCC"}"
						attach-files-label="{translate key="common.attachFiles"}"
						attached-files-label="{translate key="semicolon" label=$attachedFilesLabel}"
						bcc-label="{translate key="semicolon" label=$bccLabel}"
						body-label="{translate key="stageParticipants.notify.message"}"
						cc-label="{translate key="semicolon" label=$ccLabel}"
						deselect-label="{translate key="common.deselect"}"
						:email-templates-api-url="emailTemplatesApiUrl"
						find-template-label="{translate key="common.findTemplate"}"
						load-template-label="{translate key="common.loadTemplate"}"
						more-search-results-label="{translate key="common.numberedMore"}"
						remove-item-label="{translate key="common.removeItem"}"
						searching-label="{translate key="common.searching"}"
						subject-label="{translate key="semicolon" label=$subjectLabel}"
						switch-to-label="{translate key="semicolon" label=$switchTolabel}"
						to-label="{translate key="semicolon" label=$toLabel}"
						@set="updateStep"
					>
						<template slot="description">
							<h2>{{ step.name }}</h2>
							<p v-html="step.description"></p>
						</template>
					</composer>
				</template>
				<template v-else-if="step.type === 'promoteFiles'">
					<panel>
						<panel-section>
							<template slot="header">
								<h2>{{ step.name }}</h2>
								<p>{{ step.description }}</p>
							</template>
							<list-panel
								v-for="(list, i) in step.lists"
								:key="i"
								:items="list.files"
								:title="list.name"
							>
								<template v-slot:item="{ldelim}item{rdelim}">
									<select-submission-file-list-item
										:document-type="item.documentType"
										download-label="{translate key="common.download"}"
										:genre-is-primary="item.genreIsPrimary"
										:genre-name="item.genreName"
										:file-id="item.id"
										:name="localize(item.name)"
										:url="item.url"
									>
										<input
											type="checkbox"
											:value="item.id"
											v-model="step.selected"
										/>
									</select>
								</template>
							</list-panel>
						</panel-section>
					</panel>
				</template>
			</step>
		</steps>

		<div class="decision__footer" :class="{ldelim}'decision__footer--noSteps': !workflow.length{rdelim}">
			<spinner v-if="isSubmitting"></spinner>
			<pkp-button
				:disabled="isSubmitting"
				:is-warnable="true"
				@click="cancel"
			>
				{translate key="common.cancel"}
			</pkp-button>
			<pkp-button
				v-if="!isOnFirstStep && workflow.length > 1"
				:disabled="isSubmitting"
				@click="previousStep"
			>
				{translate key="help.previous"}
			</pkp-button>
			<pkp-button
				:disabled="isSubmitting"
				:is-primary="isOnLastStep"
				@click="nextStep"
			>
				<template v-if="isOnLastStep">
					{translate key="editor.decision.recordDecision"}
				</template>
				<template v-else>
					{translate key="help.next"}
				</template>
			</pkp-button>
			<button
				class="decision__skipStep -linkButton"
				v-if="currentStep.type === 'email' && currentStep.canSkip"
				:disabled="isSubmitting"
				@click="toggleSkippedStep(currentStep.id)"
			>
				<template v-if="skippedSteps.includes(currentStep.id)">
					{translate key="editor.decision.dontSkipEmail"}
				</template>
				<template v-else>
					{translate key="editor.decision.skipEmail"}
				</template>
			</button>
		</div>
	</div>

{/block}