<template>
	<div class="pkpListPanel" v-bind:class="classLoading">
		<div class="pkpListPanel__header">
			<div class="pkpListPanel__title">{{ i18n.title }}</div>
		</div>
		<ul class="pkpListPanel__items"></ul>
		<list-panel-count v-bind:count="itemCount" v-bind:i18n="i18n"></list-panel-count>
	</div>
</template>

<script>
import ListPanelCount from './ListPanelCount.vue';
import ListPanelSearch from './ListPanelSearch.vue';

export default {
	name: 'ListPanel',
	components: {
		ListPanelCount,
		ListPanelSearch,
	},
	data: function() {
		return {
			id: '',
			items: [],
			searchPhrase: '',
			isLoading: false,
			isSearching: false,
			config: {},
			i18n: {},
		};
	},
	computed: {
		classLoading: function() {
			return { loading: this.isLoading };
		},
		itemCount: function() {
			return this.items.length;
		},
	},
	methods: {

		/**
		 * Refresh the items in the list. This ListPanel must have a defined
		 * `get` route to execute this method.
		 *
		 * @param string statusIndicator The key for the data which should be
		 *  toggled while this action is being performed. Default: `isLoading`
		 *  corresponds with this.isLoading. The data referenced must be a bool
		 */
		refresh: function(statusIndicator) {

			if (typeof this.config.routes.get === 'undefined') {
				console.log('List refresh requested but no get route specified');
				return;
			}

			if (typeof statusIndicator === 'undefined') {
				statusIndicator = 'isLoading';
			}

			this[statusIndicator] = true;

			var self = this;
			$.get(
				this.config.routes.get.url,
				{
					searchPhrase: this.searchPhrase,
				},
				function(r) {
					self.items = JSON.parse(r);
					self[statusIndicator] = false;
				}
			);
		},

		/**
		 * Setter for the search phrase
		 *
		 * @param object data Key/value pairs for data to update:
		 *  {
		 *    name: 'value',
		 *    desc: 'value',
		 *  }
		 */
		set: function(data) {
			for (var key in data) {
				if (_.has(this, key)) {
					this[key] = data[key];
				}
			}
		}
	},
	mounted: function() {
		/**
		 * Perform a search whenever the searchPhrase is updated
		 */
		this.$watch('searchPhrase', function(newVal, oldVal) {
			if (newVal === oldVal) {
				return;
			}
			this.refresh('isSearching');
		});
	}
}
</script>
