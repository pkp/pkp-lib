<template>
	<div class="pkpListPanel__filter pkpListPanel__filter--submissions" :class="{'-isVisible': isVisible}" :aria-hidden="!isVisible">
		<div class="pkpListPanel__filterHeader pkpListPanel__filterHeader--submissions" tabindex="0">
			<span class="fa fa-filter"></span>
			{{ i18n.filter }}
		</div>
		<div class="pkpListPanel__filterOptions pkpListPanel__filterOptions--submissions">
			<div v-for="filter in filters" class="pkpListPanel__filterSet">
				<div v-if="filter.heading" class="pkpListPanel__filterSetLabel">
					{{ filter.heading }}
				</div>
				<ul>
					<li v-for="filterItem in filter.filters">
						<a href="#"
							@click.prevent.stop="filterBy(filterItem.param, filterItem.val)"
							class="pkpListPanel__filterLabel"
							:class="{'-isActive': isFilterActive(filterItem.param, filterItem.val)}"
							:tabindex="tabIndex"
						>{{ filterItem.title }}</a>
						<button
							v-if="isFilterActive(filterItem.param, filterItem.val)"
							href="#"
							class="pkpListPanel__filterRemove"
							@click.prevent.stop="clearFilter(filterItem.param, filterItem.val)"
						>
							<span class="fa fa-times-circle-o"></span>
							<span class="pkpListPanel__filterRemoveLabel">{{ __('filterRemove', {filterTitle: filterItem.title}) }}</span>
						</button>
					</li>
				</ul>
			</div>
		</div>
	</div>
</template>

<script>
import ListPanelFilter from '../ListPanelFilter.vue';

export default {
	extends: ListPanelFilter,
	name: 'SubmissionsListFilter',
	props: ['isVisible', 'filters', 'i18n'],
	methods: {
		/**
		 * Add a filter
		 */
		filterBy: function(type, val) {
			if (type === 'isIncomplete') {
				this.filterByIncomplete();
				return;
			// Deactivate the isIncomplete filter when any other filter is selected
			} else if (this.isFilterActive('isIncomplete', true)) {
				this.clearFilter('isIncomplete', true);
			}
			if (this.isFilterActive(type, val)) {
				this.clearFilter(type, val);
				return;
			}
			this.activeFilters.push({type: type, val: val});
			this.filterList(this.compileFilterParams());
		},

		/**
		 * Filter to show any incomplete submissions.
		 * These are submissions which have been started but not fully submitted
		 * by the author.
		 */
		filterByIncomplete: function() {
			if (this.isFilterActive('isIncomplete', true)) {
				this.clearFilters();
				return;
			}
			this.clearFilters();
			this.activeFilters.push({type: 'isIncomplete', val: true});
			this.filterList(this.compileFilterParams());
		},
	},
};
</script>
