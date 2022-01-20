/**
 * @file js/load.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Common configuration for building the Javascript package
 */

// Vue lib and custom mixins
import Vue from 'vue';
import GlobalMixins from '@/mixins/global.js';
import VModal from 'vue-js-modal';
import VTooltip from 'v-tooltip';
import VueScrollTo from 'vue-scrollto';

// Global components of UI Library
import Badge from '@/components/Badge/Badge.vue';
import Icon from '@/components/Icon/Icon.vue';
import PkpButton from '@/components/Button/Button.vue';
import Spinner from '@/components/Spinner/Spinner.vue';
import Tab from '@/components/Tabs/Tab.vue';
import Tabs from '@/components/Tabs/Tabs.vue';

// Helper for initializing and tracking Vue controllers
import VueRegistry from './classes/VueRegistry.js';

Vue.use(VModal, {
	dynamic: true,
	injectModalsContainer: true
});
Vue.use(VTooltip, {defaultTrigger: 'click'});
Vue.use(VueScrollTo);
Vue.mixin(GlobalMixins);

// Register global components
Vue.component('Badge', Badge);
Vue.component('Icon', Icon);
Vue.component('PkpButton', PkpButton);
Vue.component('Spinner', Spinner);
Vue.component('Tab', Tab);
Vue.component('Tabs', Tabs);

export default {
	Vue: Vue,
	registry: VueRegistry,
	eventBus: new Vue(),
	const: {},
	localeKeys: {},
	currentUser: null
};
