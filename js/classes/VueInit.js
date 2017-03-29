import Vue from 'vue';

/**
 * Global mixins
 *
 * Global mixins affect every single Vue instance created, so they should be
 * be used as little as possible. In most cases, prefer creating a mixin that
 * can be loaded into a component on-demand.
 *
 * @see https://vuejs.org/v2/guide/mixins.html
 */
Vue.mixin({

	methods: {

		/**
		 * Compile a string translation
		 *
		 * This method can be used in templates:
		 *
		 * {{ __('key') }}
		 *
		 * And parameters can be passed in:
		 *
		 * {{ __('key', { count: this.item_count }) }}
		 *
		 * @param string key The translation string to use
		 * @param params object (Optional) Variables to compile with the translation
		 * @return string
		 */
		__: function(key, params) {

			if (typeof this.i18n[key] === 'undefined') {
				console.log('Translation key ' + key + ' could not be found.');
				return '';
			}

			if (typeof params === 'undefined') {
				return this.i18n[key];
			}

			var str = this.i18n[key];
			for (var param in params) {
				str = str.replace('{$' + param + '}', params[param]);
			}
			return str;
		},

		/**
		 * Display an error message from an ajax request
		 *
		 * This callback expects to be attached to the `error` param of the
		 * jQuery $.ajax method. It can also be fired directly, but should have
		 * a jQuery response object with the following:
		 * {
		 *   responseJSON: {
		 *     error: 'localised.string.key',
		 *     errorMessage: 'The string rendered into localised form for display',
		 *   }
		 * }
		 *
		 * @todo show errors in a nice notification
		 * @param object r The response from jQuery's ajax request
		 * @return null
		 */
		ajaxErrorCallback: function(r) {
			if (_.has(r, 'responseJSON') && _.has(r.responseJSON, 'errorMessage')) {
				alert(r.responseJSON.errorMessage);
			} else {
				alert($.pkp.locale.api_submissions_unknownError);
			}
		},
	}
});

export default Vue;
