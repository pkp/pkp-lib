/**
 * @file js/load.js
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Common configuration for building the Javascript package
 */

// Vue lib and custom mixins
import Vue from 'vue';
import GlobalMixins from '@/mixins/global.js';

// Helper for initializing and tracking Vue controllers
import VueRegistry from './classes/VueRegistry.js';

Vue.mixin(GlobalMixins);

export default {
	Vue: Vue,
	registry: VueRegistry,
	eventBus: new Vue(),
	const: {},
	/**
	 * Helper function to determine if the current user has a role
	 *
	 * @param string|array role The key name of the role to check for
	 * @return bool
	 */
	userHasRole: function (role) {

		if (typeof role === 'string') {
			role = [role];
		}

		for (var r in role) {
			if ($.pkp.currentUser.accessRoles.indexOf($.pkp.app.accessRoles[role[r]]) > -1) {
				return true;
			}
		}

		return false;
	},
};
