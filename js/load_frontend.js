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

// Base Button Components
import BaseButton from '@/frontend/components/PkpButton/BaseButton.vue';

// Base Textarea Components
import BaseTextarea from '@/frontend/components/PkpTextarea/BaseTextarea.vue';
import BaseTextareaInput from '@/frontend/components/PkpTextarea/BaseTextareaInput.vue';
import BaseTextareaLabel from '@/frontend/components/PkpTextarea/BaseTextareaLabel.vue';

// Base Dialog Components
import BaseDialog from '@/frontend/components/PkpDialog/base/BaseDialog.vue';
import BaseDialogActionButtons from '@/frontend/components/PkpDialog/base/BaseDialogActionButtons.vue';
import BaseDialogBody from '@/frontend/components/PkpDialog/base/BaseDialogBody.vue';
import BaseDialogContent from '@/frontend/components/PkpDialog/base/BaseDialogContent.vue';
import BaseDialogOverlay from '@/frontend/components/PkpDialog/base/BaseDialogOverlay.vue';
import BaseDialogTitle from '@/frontend/components/PkpDialog/base/BaseDialogTitle.vue';

// Base Dropdown Menu Components
import BaseDropdownMenu from '@/frontend/components/PkpDropdownMenu/base/BaseDropdownMenu.vue';
import BaseDropdownMenuItem from '@/frontend/components/PkpDropdownMenu/base/BaseDropdownMenuItem.vue';
import BaseDropdownMenuItemLabel from '@/frontend/components/PkpDropdownMenu/base/BaseDropdownMenuItemLabel.vue';
import BaseDropdownMenuItems from '@/frontend/components/PkpDropdownMenu/base/BaseDropdownMenuItems.vue';
import BaseDropdownMenuTrigger from '@/frontend/components/PkpDropdownMenu/base/BaseDropdownMenuTrigger.vue';
import BaseDropdownMenuTriggerIcon from '@/frontend/components/PkpDropdownMenu/base/BaseDropdownMenuTriggerIcon.vue';

// Base Icon Components
import BaseIcon from '@/frontend/components/PkpIcon/BaseIcon.vue';

// Base Comments Components
import BaseComments from '@/frontend/components/PkpComments/base/BaseComments.vue';
import BaseCommentsLogInto from '@/frontend/components/PkpComments/base/BaseCommentsLogInto.vue';
import BaseCommentsMessage from '@/frontend/components/PkpComments/base/BaseCommentsMessage.vue';
import BaseCommentsMessageActions from '@/frontend/components/PkpComments/base/BaseCommentsMessageActions.vue';
import BaseCommentsMessageAuthor from '@/frontend/components/PkpComments/base/BaseCommentsMessageAuthor.vue';
import BaseCommentsMessageAuthorAffiliation from '@/frontend/components/PkpComments/base/BaseCommentsMessageAuthorAffiliation.vue';
import BaseCommentsMessageAuthorName from '@/frontend/components/PkpComments/base/BaseCommentsMessageAuthorName.vue';
import BaseCommentsMessageAuthorOrcid from '@/frontend/components/PkpComments/base/BaseCommentsMessageAuthorOrcid.vue';
import BaseCommentsMessageBody from '@/frontend/components/PkpComments/base/BaseCommentsMessageBody.vue';
import BaseCommentsMessageDate from '@/frontend/components/PkpComments/base/BaseCommentsMessageDate.vue';
import BaseCommentsMessages from '@/frontend/components/PkpComments/base/BaseCommentsMessages.vue';
import BaseCommentsNew from '@/frontend/components/PkpComments/base/BaseCommentsNew.vue';
import BaseCommentsNewInput from '@/frontend/components/PkpComments/base/BaseCommentsNewInput.vue';
import BaseCommentsNewSubmit from '@/frontend/components/PkpComments/base/BaseCommentsNewSubmit.vue';
import BaseCommentsNotificationMessageNeedsApproval from '@/frontend/components/PkpComments/base/BaseCommentsNotificationMessageNeedsApproval.vue';
import BaseCommentsNotificationNotLatest from '@/frontend/components/PkpComments/base/BaseCommentsNotificationNotLatest.vue';
import BaseCommentsVersion from '@/frontend/components/PkpComments/base/BaseCommentsVersion.vue';
import BaseCommentsVersionContent from '@/frontend/components/PkpComments/base/BaseCommentsVersionContent.vue';
import BaseCommentsVersionHeader from '@/frontend/components/PkpComments/base/BaseCommentsVersionHeader.vue';
import BaseCommentsVersionHeaderChevron from '@/frontend/components/PkpComments/base/BaseCommentsVersionHeaderChevron.vue';
import BaseCommentsVersionHeaderLabel from '@/frontend/components/PkpComments/base/BaseCommentsVersionHeaderLabel.vue';
import BaseCommentsVersionHeaderTrigger from '@/frontend/components/PkpComments/base/BaseCommentsVersionHeaderTrigger.vue';
import BaseCommentsVersions from '@/frontend/components/PkpComments/base/BaseCommentsVersions.vue';
import BaseScrollToComments from '@/frontend/components/PkpComments/base/BaseScrollToComments.vue';
import BaseScrollToCommentsAllComments from '@/frontend/components/PkpComments/base/BaseScrollToCommentsAllComments.vue';
import BaseScrollToCommentsLogInto from '@/frontend/components/PkpComments/base/BaseScrollToCommentsLogInto.vue';
import BaseCommentReportDialog from '@/frontend/components/PkpComments/base/BaseCommentReportDialog.vue';
import BaseCommentReportDialogAuthor from '@/frontend/components/PkpComments/base/BaseCommentReportDialogAuthor.vue';
import BaseCommentReportDialogCommentText from '@/frontend/components/PkpComments/base/BaseCommentReportDialogCommentText.vue';
import BaseCommentReportDialogReasonInput from '@/frontend/components/PkpComments/base/BaseCommentReportDialogReasonInput.vue';

// Helper for initializing and tracking Vue controllers
import VueRegistry from './classes/VueRegistry.js';

VueRegistry.registerDirective('strip-unsafe-html', stripUnsafeHtml);

VueRegistry.registerComponent('PkpButton', PkpButton);
VueRegistry.registerComponent('PkpDialog', PkpDialog);
VueRegistry.registerComponent('PkpModalManager', PkpModalManager);
VueRegistry.registerComponent('PkpTextarea', PkpTextarea);
VueRegistry.registerComponent('PkpDropdownMenu', PkpDropdownMenu);
VueRegistry.registerComponent('PkpIcon', PkpIcon);
VueRegistry.registerComponent('PkpScrollToComments', PkpScrollToComments);
VueRegistry.registerComponent('PkpComments', PkpComments);
VueRegistry.registerComponent('PkpCommentReportDialog', PkpCommentReportDialog);

// Register Base Button Components
VueRegistry.registerComponent('BaseButton', BaseButton);

// Register Base Textarea Components
VueRegistry.registerComponent('BaseTextarea', BaseTextarea);
VueRegistry.registerComponent('BaseTextareaInput', BaseTextareaInput);
VueRegistry.registerComponent('BaseTextareaLabel', BaseTextareaLabel);

// Register Base Dialog Components
VueRegistry.registerComponent('BaseDialog', BaseDialog);
VueRegistry.registerComponent(
	'BaseDialogActionButtons',
	BaseDialogActionButtons,
);
VueRegistry.registerComponent('BaseDialogBody', BaseDialogBody);
VueRegistry.registerComponent('BaseDialogContent', BaseDialogContent);
VueRegistry.registerComponent('BaseDialogOverlay', BaseDialogOverlay);
VueRegistry.registerComponent('BaseDialogTitle', BaseDialogTitle);

// Register Base Dropdown Menu Components
VueRegistry.registerComponent('BaseDropdownMenu', BaseDropdownMenu);
VueRegistry.registerComponent('BaseDropdownMenuItem', BaseDropdownMenuItem);
VueRegistry.registerComponent(
	'BaseDropdownMenuItemLabel',
	BaseDropdownMenuItemLabel,
);
VueRegistry.registerComponent('BaseDropdownMenuItems', BaseDropdownMenuItems);
VueRegistry.registerComponent(
	'BaseDropdownMenuTrigger',
	BaseDropdownMenuTrigger,
);
VueRegistry.registerComponent(
	'BaseDropdownMenuTriggerIcon',
	BaseDropdownMenuTriggerIcon,
);

// Register Base Icon Components
VueRegistry.registerComponent('BaseIcon', BaseIcon);

// Register Base Comments Components
VueRegistry.registerComponent('BaseComments', BaseComments);
VueRegistry.registerComponent('BaseCommentsLogInto', BaseCommentsLogInto);
VueRegistry.registerComponent('BaseCommentsMessage', BaseCommentsMessage);
VueRegistry.registerComponent(
	'BaseCommentsMessageActions',
	BaseCommentsMessageActions,
);
VueRegistry.registerComponent(
	'BaseCommentsMessageAuthor',
	BaseCommentsMessageAuthor,
);
VueRegistry.registerComponent(
	'BaseCommentsMessageAuthorAffiliation',
	BaseCommentsMessageAuthorAffiliation,
);
VueRegistry.registerComponent(
	'BaseCommentsMessageAuthorName',
	BaseCommentsMessageAuthorName,
);
VueRegistry.registerComponent(
	'BaseCommentsMessageAuthorOrcid',
	BaseCommentsMessageAuthorOrcid,
);
VueRegistry.registerComponent(
	'BaseCommentsMessageBody',
	BaseCommentsMessageBody,
);
VueRegistry.registerComponent(
	'BaseCommentsMessageDate',
	BaseCommentsMessageDate,
);
VueRegistry.registerComponent('BaseCommentsMessages', BaseCommentsMessages);
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
VueRegistry.registerComponent('BaseCommentsVersion', BaseCommentsVersion);
VueRegistry.registerComponent(
	'BaseCommentsVersionContent',
	BaseCommentsVersionContent,
);
VueRegistry.registerComponent(
	'BaseCommentsVersionHeader',
	BaseCommentsVersionHeader,
);
VueRegistry.registerComponent(
	'BaseCommentsVersionHeaderChevron',
	BaseCommentsVersionHeaderChevron,
);
VueRegistry.registerComponent(
	'BaseCommentsVersionHeaderLabel',
	BaseCommentsVersionHeaderLabel,
);
VueRegistry.registerComponent(
	'BaseCommentsVersionHeaderTrigger',
	BaseCommentsVersionHeaderTrigger,
);
VueRegistry.registerComponent('BaseCommentsVersions', BaseCommentsVersions);
VueRegistry.registerComponent('BaseScrollToComments', BaseScrollToComments);
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
	'BaseCommentReportDialogCommentText',
	BaseCommentReportDialogCommentText,
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
	},
	pkpCreateVueApp,
	createApp,
	registry: VueRegistry,
	const: {},
	localeKeys: {},
	currentUser: null,
};
