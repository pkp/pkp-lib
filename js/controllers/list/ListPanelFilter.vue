<template>
	<div class="pkpListPanel__filter" :class="{'-isVisible': this.isVisible}">
		<div class="pkpListPanel__filterHeader" tabindex="0">
			<span class="fa fa-filter"></span>
			{{ i18n.filter }}
		</div>
		<div class="pkpListPanel__filterOptions"></div>
	</div>
</template>

<script>
export default {
	name: 'ListPanelFilter',
	props: ['i18n', 'isVisible'],
	data: function() {
		return {
			activeFilters: [],
		}
	},
	computed: {
		tabIndex: function() {
			return this.isVisible ? false : -1;
		}
	},
	methods: {
		/**
		 * Check if a filter is currently active
		 */
		isFilterActive: function(type, val) {
			return this.activeFilters.filter(filter => {
				return filter.type === type && filter.val === val;
			}).length
		},

		/**
		 * Add a filter
		 */
		filterBy: function(type, val) {
			if (this.isFilterActive(type, val)) {
				this.clearFilter(type, val);
				return;
			}
			this.activeFilters.push({type: type, val: val});
			this.filterList(this.compileFilterParams());
		},

		/**
		 * Remove a filter
		 */
		clearFilter: function(type, val) {
			this.activeFilters = this.activeFilters.filter(filter => {
				return filter.type !== type || filter.val !== val;
			});
			this.filterList(this.compileFilterParams());
		},

		/**
		 * Clear any filters that are currently active
		 */
		clearFilters: function() {
			this.activeFilters = [];
			this.filterList({});
		},

		/**
		 * Compile active filters into filter parameters
		 */
		compileFilterParams: function() {
			let params = {};
			for (var filter of this.activeFilters) {
				if (params[filter.type] === undefined) {
					params[filter.type] = [];
				}
				params[filter.type].push(filter.val);
			}
			return params;
		},

		/**
		 * Emit an event to filter items in the list panel
		 */
		filterList: function(data) {
			this.$emit('filterList', data);
		},
	},
	mounted: function() {
		/**
		 * Set focus in filters whenever the visible status is initiated
		 */
		this.$watch('isVisible', function(newVal, oldVal) {
			if (!newVal || newVal === oldVal) {
				return;
			}
			this.$el.querySelector('.pkpListPanel__filterHeader').focus();
		});
	}
}
</script>
