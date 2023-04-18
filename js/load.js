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
import VueAnnouncer from 'vue-announcer';
import VModal from 'vue-js-modal';
import VTooltip from 'v-tooltip';
import VueScrollTo from 'vue-scrollto';

// Global components of UI Library
import Badge from '@/components/Badge/Badge.vue';
import Dropdown from '@/components/Dropdown/Dropdown.vue';
import Icon from '@/components/Icon/Icon.vue';
import Notification from '@/components/Notification/Notification.vue';
import Panel from '@/components/Panel/Panel.vue';
import PanelSection from '@/components/Panel/PanelSection.vue';
import PkpButton from '@/components/Button/Button.vue';
import PkpHeader from '@/components/Header/Header.vue';
import Spinner from '@/components/Spinner/Spinner.vue';
import Step from '@/components/Steps/Step.vue';
import Steps from '@/components/Steps/Steps.vue';
import Tab from '@/components/Tabs/Tab.vue';
import Tabs from '@/components/Tabs/Tabs.vue';

// Helper for initializing and tracking Vue controllers
import VueRegistry from './classes/VueRegistry.js';

Vue.use(VueAnnouncer);
Vue.use(VModal, {
	dynamic: true,
	injectModalsContainer: true,
});
Vue.use(VTooltip, {defaultTrigger: 'click'});
Vue.use(VueScrollTo);
Vue.mixin(GlobalMixins);

// Register global components
Vue.component('Badge', Badge);
Vue.component('Dropdown', Dropdown);
Vue.component('Icon', Icon);
Vue.component('Notification', Notification);
Vue.component('Panel', Panel);
Vue.component('PanelSection', PanelSection);
Vue.component('PkpButton', PkpButton);
Vue.component('PkpHeader', PkpHeader);
Vue.component('Spinner', Spinner);
Vue.component('Step', Step);
Vue.component('Steps', Steps);
Vue.component('Tab', Tab);
Vue.component('Tabs', Tabs);

export default {
	Vue: Vue,
	registry: VueRegistry,
	eventBus: new Vue(),
	const: {},
	localeKeys: {},
	currentUser: null,
};
