<template>
	<div class="pkpListPanel" :class="classLoading">
		<div class="pkpListPanel__header">
			<div class="pkpListPanel__title">{{ i18n.title }}</div>
		</div>
		<ul class="pkpListPanel__items"></ul>
	</div>
</template>

<script>
import ListPanelCount from './ListPanelCount.vue';
import ListPanelSearch from './ListPanelSearch.vue';
import ListPanelLoadMore from './ListPanelLoadMore.vue';

export default {
	name: 'ListPanel',
	components: {
		ListPanelCount,
		ListPanelSearch,
		ListPanelLoadMore,
	},
	data: function() {
		return {
			id: '',
			collection: {
				items: [],
				maxItems: null,
			},
			searchPhrase: '',
			isLoading: false,
			isSearching: false,
			config: {},
			i18n: {},
		};
	},
	computed: {
		classLoading: function() {
			return { '--isLoading': this.isLoading };
		},
		itemCount: function() {
			return this.collection.items.length;
		},
		canLoadMore: function() {
			return typeof this.collection.maxItems !== 'undefined' && this.collection.maxItems > this.itemCount;
		},
	},
	methods: {

		/**
		 * Setter for child components.
		 *
		 * If child components want to modify state in the parent component,
		 * they need to emit an event that ListPanel can capture to make the
		 * modification. This setter acts as a simple API, allowing components
		 * to pass event data and have that event passed to this method to
		 * set the data.
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
		},

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
					range: this.config.range,
				},
				function(r) {

					self[statusIndicator] = false;

					if (typeof r === 'undefined') {
						console.log('No response received from refresh request in ListPanel');
						return false;
					}

					self.collection = JSON.parse(r);
				}
			);
		},

		/**
		 * Load more items in the list
		 */
		loadMore: function() {
			this.isLoading = true;

			if (typeof this.config.routes.get === 'undefined') {
				console.log('List loadMore requested but no get route specified');
				return;
			}

			this.config.range.page++;

			var self = this;
			$.get(
				this.config.routes.get.url,
				{
					searchPhrase: this.searchPhrase,
					range: this.config.range,
				},
				function(r) {

					self.isLoading = false;

					if (typeof r === 'undefined') {
						console.log('No response received from laodMore request in ListPanel');
						return false;
					}

					var result = JSON.parse(r),
						existingItemIds = _.pluck(self.items, 'id');

					_.each(result.items, function(item) {
						if (existingItemIds.indexOf(item.id) < 0) {
							self.collection.items.push(item);
						}
					})
					self.collection.maxItems = result.maxItems;
				}
			);
		},
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
