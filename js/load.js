/**
 * @file js/load.js
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Common configuration for building the Javascript package
 */

// Vue lib and custom mixins
import Vue from 'vue';
import GlobalMixins from '@/mixins/global.js';
import VTooltip from 'v-tooltip';
import VueScrollTo from 'vue-scrollto';

// Helper for initializing and tracking Vue controllers
import VueRegistry from './classes/VueRegistry.js';

Vue.use(VTooltip, {defaultTrigger: 'click'});
Vue.use(VueScrollTo);
Vue.mixin(GlobalMixins);

export default {
	Vue: Vue,
	registry: VueRegistry,
	eventBus: new Vue(),
	const: {},
	/**
	 * Helper function to determine if the current user has a role
	 *
	 * @param int|array roles The role ID to look for (pkp.const.ROLE_ID...)
	 * @return bool
	 */
	userHasRole: function(roles) {
		if (!Array.isArray(roles)) {
			roles = [roles];
		}

		var hasRole = false;
		roles.forEach(role => {
			if ($.pkp.currentUser.accessRoles.indexOf(role) > -1) {
				hasRole = true;
			}
		});

		return hasRole;
	}
};
