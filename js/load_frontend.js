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

// Base Comments Components
import BaseComments from '@/frontend/components/PkpComments/base/BaseComments.vue';
import BaseCommentsLogInto from '@/frontend/components/PkpComments/base/BaseCommentsLogInto.vue';
import BaseCommentsMessageActions from '@/frontend/components/PkpComments/base/BaseCommentsMessageActions.vue';
import BaseCommentsNew from '@/frontend/components/PkpComments/base/BaseCommentsNew.vue';
import BaseCommentsNewInput from '@/frontend/components/PkpComments/base/BaseCommentsNewInput.vue';
import BaseCommentsNewSubmit from '@/frontend/components/PkpComments/base/BaseCommentsNewSubmit.vue';
import BaseCommentsNotificationMessageNeedsApproval from '@/frontend/components/PkpComments/base/BaseCommentsNotificationMessageNeedsApproval.vue';
import BaseCommentsNotificationNotLatest from '@/frontend/components/PkpComments/base/BaseCommentsNotificationNotLatest.vue';
import BaseScrollToCommentsAllComments from '@/frontend/components/PkpComments/base/BaseScrollToCommentsAllComments.vue';
import BaseScrollToCommentsLogInto from '@/frontend/components/PkpComments/base/BaseScrollToCommentsLogInto.vue';
import BaseCommentReportDialog from '@/frontend/components/PkpComments/base/BaseCommentReportDialog.vue';
import BaseCommentReportDialogAuthor from '@/frontend/components/PkpComments/base/BaseCommentReportDialogAuthor.vue';
import BaseCommentReportDialogReasonInput from '@/frontend/components/PkpComments/base/BaseCommentReportDialogReasonInput.vue';

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

// Register Base Comments Components
VueRegistry.registerComponent('BaseComments', BaseComments);
VueRegistry.registerComponent('BaseCommentsLogInto', BaseCommentsLogInto);
VueRegistry.registerComponent(
	'BaseCommentsMessageActions',
	BaseCommentsMessageActions,
);
VueRegistry.registerComponent('BaseCommentsNew', BaseCommentsNew);
VueRegistry.registerComponent('BaseCommentsNewInput', BaseCommentsNewInput);
VueRegistry.registerComponent('BaseCommentsNewSubmit', BaseCommentsNewSubmit);
VueRegistry.registerComponent(
	'BaseCommentsNotificationMessageNeedsApproval',
	BaseCommentsNotificationMessageNeedsApproval,
);
VueRegistry.registerComponent(
	'BaseCommentsNotificationNotLatest',
	BaseCommentsNotificationNotLatest,
);
VueRegistry.registerComponent(
	'BaseScrollToCommentsAllComments',
	BaseScrollToCommentsAllComments,
);
VueRegistry.registerComponent(
	'BaseScrollToCommentsLogInto',
	BaseScrollToCommentsLogInto,
);
VueRegistry.registerComponent(
	'BaseCommentReportDialog',
	BaseCommentReportDialog,
);
VueRegistry.registerComponent(
	'BaseCommentReportDialogAuthor',
	BaseCommentReportDialogAuthor,
);
VueRegistry.registerComponent(
	'BaseCommentReportDialogReasonInput',
	BaseCommentReportDialogReasonInput,
);

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
