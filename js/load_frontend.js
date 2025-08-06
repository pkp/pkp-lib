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

// Directives
import {stripUnsafeHtml} from '@/directives/stripUnsafeHtml';

// Global components of UI Library
import PkpModalManager from '@/frontend/components/PkpModal/PkpModalManager.vue';
import PkpHelloWorld from '@/frontend/components/PkpHelloWorld/PkpHelloWorld.vue';
import PkpButton from '@/frontend/components/PkpButton/PkpButton.vue';
import PkpAccordion from '@/frontend/components/PkpAccordion/PkpAccordion.vue';
import PkpTextarea from '@/frontend/components/PkpTextarea/PkpTextarea.vue';
import PkpDropdownActions from '@/frontend/components/PkpDropdownActions/PkpDropdownActions.vue';

// Helper for initializing and tracking Vue controllers
import VueRegistry from './classes/VueRegistry.js';

VueRegistry.registerDirective('strip-unsafe-html', stripUnsafeHtml);

VueRegistry.registerComponent('PkpHelloWorld', PkpHelloWorld);
VueRegistry.registerComponent('PkpButton', PkpButton);
VueRegistry.registerComponent('PkpAccordion', PkpAccordion);
VueRegistry.registerComponent('PkpModalManager', PkpModalManager);
VueRegistry.registerComponent('PkpTextarea', PkpTextarea);
VueRegistry.registerComponent('PkpDropdownActions', PkpDropdownActions);

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
	},
	pkpCreateVueApp,
	createApp,
	registry: VueRegistry,
	const: {},
	localeKeys: {},
	currentUser: null,
};
