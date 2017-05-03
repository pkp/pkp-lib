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
			count: 20,
			offset: 0,
			apiPath: '',
			getParams: {},
			i18n: {},
			lazyLoad: false,
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
		 * Get items for the list. This ListPanel must have a defined
		 * `get` route to execute this method.
		 *
		 * @param string statusIndicator The key for the data which should be
		 *  toggled while this action is being performed. Default: `isLoading`
		 *  corresponds with this.isLoading. The data referenced must be a bool
		 * @param string handleResponse How to handle the response. `append` to
		 *  add to the collection. Default: null will replace the collection.
		 */
		get: function(statusIndicator, handleResponse) {

			if (typeof statusIndicator === 'undefined') {
				statusIndicator = 'isLoading';
			}

			this[statusIndicator] = true;

			var self = this;
			$.ajax({
				url: $.pkp.app.apiBaseUrl + '/' + this.apiPath,
				type: 'GET',
				data: _.extend(
					{},
					this.getParams,
					{
						searchPhrase: this.searchPhrase,
						count: this.count,
						offset: this.offset,
					},
				),
				error: this.ajaxErrorCallback,
				success: function(r) {

					if (handleResponse === 'append') {
						var existingItemIds = _.pluck(self.items, 'id');
						_.each(r.items, function(item) {
							if (existingItemIds.indexOf(item.id) < 0) {
								self.collection.items.push(item);
							}
						})
						self.collection.maxItems = r.maxItems;
					} else {
						self.collection = r;
					}
				},
				complete: function(r) {
					self[statusIndicator] = false;
				}
			});
		},

		/**
		 * Load more items in the list
		 */
		loadMore: function() {
			this.offset = this.collection.items.length;
			this.get('isLoading', 'append');
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
			this.offset = 0;
			this.get('isSearching');
		});

		/**
		 * Load a collection into the component once the page is loaded if a
		 * lazyLoad is requested.
		 */
		if (this.lazyLoad) {
			if (document.readyState === 'complete') {
				this.get();
			} else {
				var self = this;
				$(function() {
					self.get();
				});
			}
		}
	}
}
</script>
