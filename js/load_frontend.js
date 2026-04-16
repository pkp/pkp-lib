/**
 * @file js/load.js
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Common configuration for building the Javascript package
 */

// Vue lib and custom mixins
import {createApp} from 'vue';
import * as vue from 'vue';

import {createPinia} from 'pinia';

// Composables
import * as usePkpApp from '@/frontend/composables/usePkpApp.js';
import * as usePkpUrl from '@/frontend/composables/usePkpUrl.js';
import * as usePkpFetch from '@/frontend/composables/usePkpFetch.js';
import * as usePkpFetchPaginated from '@/frontend/composables/usePkpFetchPaginated.js';
import * as usePkpModal from '@/frontend/composables/usePkpModal.js';
import * as usePkpLocalize from '@/frontend/composables/usePkpLocalize';
import * as usePkpDate from '@/frontend/composables/usePkpDate';
import * as usePkpStyles from '@/frontend/composables/usePkpStyles.js';

// Directives
import {stripUnsafeHtml} from '@/directives/stripUnsafeHtml';

// Global components of UI Library
import PkpModalManager from '@/frontend/components/PkpModalManager/PkpModalManager.vue';
import PkpDialog from '@/frontend/components/PkpDialog/PkpDialog.vue';
import PkpButton from '@/frontend/components/PkpButton/PkpButton.vue';
import PkpTextarea from '@/frontend/components/PkpTextarea/PkpTextarea.vue';
import PkpDropdownMenu from '@/frontend/components/PkpDropdownMenu/PkpDropdownMenu.vue';
import PkpIcon from '@/frontend/components/PkpIcon/PkpIcon.vue';
import PkpScrollToComments from '@/frontend/components/PkpComments/PkpScrollToComments.vue';
import PkpComments from '@/frontend/components/PkpComments/PkpComments.vue';
import PkpCommentReportDialog from '@/frontend/components/PkpComments/PkpCommentReportDialog.vue';
import PkpOpenReview from '@/frontend/components/PkpOpenReview/PkpOpenReview.vue';
import PkpOpenReviewSummary from '@/frontend/components/PkpOpenReview/PkpOpenReviewSummary.vue';
import PkpCombobox from '@/frontend/components/PkpCombobox/PkpCombobox.vue';
import PkpCiteBody from '@/frontend/components/PkpCite/PkpCiteBody.vue';
import PkpCrossmarkButton from '@/frontend/components/PkpCrossmarkButton/PkpCrossmarkButton.vue';
import PkpUsageChart from '@/frontend/components/PkpUsageChart/PkpUsageChart.vue';

// Pinia stores
import {usePkpModalStore} from '@/frontend/stores/pkpModalStore';
import {usePageStore} from '@/frontend/stores/pkpPageStore';
import {usePkpCommentsStore} from '@/frontend/components/PkpComments/usePkpCommentsStore';
import {usePkpOpenReviewStore} from '@/frontend/components/PkpOpenReview/usePkpOpenReviewStore';
import {usePkpCiteStore} from '@/frontend/components/PkpCite/usePkpCiteStore';
import {usePkpUsageChartStore} from '@/frontend/components/PkpUsageChart/usePkpUsageChartStore';

// PkpTab Components
import PkpTabRoot from '@/frontend/components/PkpTab/PkpTabRoot.vue';
import PkpTabList from '@/frontend/components/PkpTab/PkpTabList.vue';
import PkpTabTrigger from '@/frontend/components/PkpTab/PkpTabTrigger.vue';
import PkpTabContent from '@/frontend/components/PkpTab/PkpTabContent.vue';

// PkpAccordion Components
import PkpAccordionRoot from '@/frontend/components/PkpAccordion/PkpAccordionRoot.vue';
import PkpAccordionItem from '@/frontend/components/PkpAccordion/PkpAccordionItem.vue';
import PkpAccordionHeader from '@/frontend/components/PkpAccordion/PkpAccordionHeader.vue';
import PkpAccordionContent from '@/frontend/components/PkpAccordion/PkpAccordionContent.vue';

// PkpComments sub-components (for use in slot overrides)
import PkpCommentsShowMore from '@/frontend/components/PkpComments/PkpCommentsShowMore.vue';
import PkpCommentsLogInto from '@/frontend/components/PkpComments/PkpCommentsLogInto.vue';
import PkpCommentsMessageActions from '@/frontend/components/PkpComments/PkpCommentsMessageActions.vue';
import PkpCommentsNew from '@/frontend/components/PkpComments/PkpCommentsNew.vue';
import PkpCommentsNewInput from '@/frontend/components/PkpComments/PkpCommentsNewInput.vue';
import PkpCommentsNewSubmit from '@/frontend/components/PkpComments/PkpCommentsNewSubmit.vue';
import PkpCommentsNotificationMessageNeedsApproval from '@/frontend/components/PkpComments/PkpCommentsNotificationMessageNeedsApproval.vue';
import PkpCommentsNotificationNotLatest from '@/frontend/components/PkpComments/PkpCommentsNotificationNotLatest.vue';
import PkpScrollToCommentsAllComments from '@/frontend/components/PkpComments/PkpScrollToCommentsAllComments.vue';
import PkpScrollToCommentsLogInto from '@/frontend/components/PkpComments/PkpScrollToCommentsLogInto.vue';
import PkpCommentReportDialogAuthor from '@/frontend/components/PkpComments/PkpCommentReportDialogAuthor.vue';
import PkpCommentReportDialogReasonInput from '@/frontend/components/PkpComments/PkpCommentReportDialogReasonInput.vue';

// Helper for initializing and tracking Vue controllers
import VueRegistry from './classes/VueRegistry.js';

VueRegistry.registerDirective('strip-unsafe-html', stripUnsafeHtml);

// Register frontend Pinia stores for lookup via pkp.registry.getPiniaStore()
VueRegistry.registerStore('pkpModal', usePkpModalStore);
VueRegistry.registerStore('pkpPage', usePageStore);
VueRegistry.registerStore('pkpComments', usePkpCommentsStore);
VueRegistry.registerStore('pkpOpenReview', usePkpOpenReviewStore);
VueRegistry.registerStore('pkpCite', usePkpCiteStore);
VueRegistry.registerStore('pkpUsageChart', usePkpUsageChartStore);

VueRegistry.registerComponent('PkpButton', PkpButton);
VueRegistry.registerComponent('PkpDialog', PkpDialog);
VueRegistry.registerComponent('PkpModalManager', PkpModalManager);
VueRegistry.registerComponent('PkpTextarea', PkpTextarea);
VueRegistry.registerComponent('PkpDropdownMenu', PkpDropdownMenu);
VueRegistry.registerComponent('PkpIcon', PkpIcon);
VueRegistry.registerComponent('PkpScrollToComments', PkpScrollToComments);
VueRegistry.registerComponent('PkpComments', PkpComments);
VueRegistry.registerComponent('PkpCommentReportDialog', PkpCommentReportDialog);
VueRegistry.registerComponent('PkpOpenReview', PkpOpenReview);
VueRegistry.registerComponent('PkpOpenReviewSummary', PkpOpenReviewSummary);
VueRegistry.registerComponent('PkpCombobox', PkpCombobox);
VueRegistry.registerComponent('PkpCiteBody', PkpCiteBody);
VueRegistry.registerComponent('PkpCrossmarkButton', PkpCrossmarkButton);
VueRegistry.registerComponent('PkpUsageChart', PkpUsageChart);

// Register PkpTab Components
VueRegistry.registerComponent('PkpTabRoot', PkpTabRoot);
VueRegistry.registerComponent('PkpTabList', PkpTabList);
VueRegistry.registerComponent('PkpTabTrigger', PkpTabTrigger);
VueRegistry.registerComponent('PkpTabContent', PkpTabContent);

// Register PkpAccordion Components
VueRegistry.registerComponent('PkpAccordionRoot', PkpAccordionRoot);
VueRegistry.registerComponent('PkpAccordionItem', PkpAccordionItem);
VueRegistry.registerComponent('PkpAccordionHeader', PkpAccordionHeader);
VueRegistry.registerComponent('PkpAccordionContent', PkpAccordionContent);

// Register PkpComments sub-components so theme plugins can use them in slot overrides
VueRegistry.registerComponent('PkpCommentsLogInto', PkpCommentsLogInto);
VueRegistry.registerComponent(
	'PkpCommentsMessageActions',
	PkpCommentsMessageActions,
);
VueRegistry.registerComponent('PkpCommentsNew', PkpCommentsNew);
VueRegistry.registerComponent('PkpCommentsNewInput', PkpCommentsNewInput);
VueRegistry.registerComponent('PkpCommentsNewSubmit', PkpCommentsNewSubmit);
VueRegistry.registerComponent(
	'PkpCommentsNotificationMessageNeedsApproval',
	PkpCommentsNotificationMessageNeedsApproval,
);
VueRegistry.registerComponent(
	'PkpCommentsNotificationNotLatest',
	PkpCommentsNotificationNotLatest,
);
VueRegistry.registerComponent(
	'PkpScrollToCommentsAllComments',
	PkpScrollToCommentsAllComments,
);
VueRegistry.registerComponent(
	'PkpScrollToCommentsLogInto',
	PkpScrollToCommentsLogInto,
);
VueRegistry.registerComponent(
	'PkpCommentReportDialogAuthor',
	PkpCommentReportDialogAuthor,
);
VueRegistry.registerComponent(
	'PkpCommentReportDialogReasonInput',
	PkpCommentReportDialogReasonInput,
);
VueRegistry.registerComponent('PkpCommentsShowMore', PkpCommentsShowMore);

const pinia = createPinia();

VueRegistry.attachPiniaInstance(pinia);

function pkpCreateVueApp(createAppArgs) {
	// Initialize Vue
	const vueApp = createApp(createAppArgs);
	vueApp.use(pinia);

	// https://github.com/vuejs/pinia/discussions/1197
	// to be able globally share stores
	vueApp.config.globalProperties.$store = {};

	// For compatibility with vue2 to preserve spaces between html tags
	vueApp.config.compilerOptions.whitespace = 'preserve';

	// register all global components
	const allGlobalComponents = VueRegistry.getAllComponents();
	Object.keys(allGlobalComponents).forEach((componentName) => {
		vueApp.component(componentName, allGlobalComponents[componentName]);
	});

	// register all global directives
	const allGlobalDirectives = VueRegistry.getAllDirectives();
	Object.keys(allGlobalDirectives).forEach((directiveName) => {
		vueApp.directive(directiveName, allGlobalDirectives[directiveName]);
	});

	return vueApp;
}

export default {
	Vue: pkpCreateVueApp({}),
	// making vue functions available via pkp.modules.vue for plugins
	// especially useful when using composition api
	modules: {
		vue,
		piniaInstance: pinia,
		usePkpApp,
		usePkpUrl,
		usePkpFetch,
		usePkpFetchPaginated,
		usePkpModal,
		usePkpLocalize,
		usePkpDate,
		usePkpStyles,
	},
	pkpCreateVueApp,
	createApp,
	registry: VueRegistry,
	const: {},
	localeKeys: {},
	currentUser: null,
};
