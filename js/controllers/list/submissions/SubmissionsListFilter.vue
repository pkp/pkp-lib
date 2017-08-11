<template>
	<div class="pkpListPanel__filter pkpListPanel__filter--submissions" :class="{'--isVisible': isVisible}" :aria-hidden="!isVisible">
		<div class="pkpListPanel__filterHeader pkpListPanel__filterHeader--submissions" tabindex="0">
			<span class="fa fa-filter"></span>
			{{ i18n.filter }}
		</div>
		<div class="pkpListPanel__filterOptions pkpListPanel__filterOptions--submissions">
			<div v-if="stages.length > 0" class="pkpListPanel__filterSet">
				<div class="pkpListPanel__filterSetLabel">
					{{ i18n.stages }}
				</div>
				<ul>
					<li v-for="stage in stages">
						<a href="#"
							@click.prevent.stop="filterBy('stageIds', stage.id)"
							class="pkpListPanel__filterLabel"
							:class="{'--isActive': isFilterActive('stageIds', stage.id)}"
							:tabindex="tabIndex"
						>{{ stage.title }}</a>
						<button
							v-if="isFilterActive('stageIds', stage.id)"
							href="#"
							class="pkpListPanel__filterRemove"
							@click.prevent.stop="clearFilter('stageIds', stage.id)"
						>
							<span class="fa fa-times-circle-o"></span>
							<span class="pkpListPanel__filterRemoveLabel">{{ __('filterRemove', {filterTitle: stage.title}) }}</span>
						</button>
					</li>
				</ul>
			</div>
			<div v-if="sections.length > 0" class="pkpListPanel__filterSet">
				<div class="pkpListPanel__filterSetLabel">
					{{ i18n.sections }}
				</div>
				<ul>
					<li v-for="section in sections">
						<a href="#"
							@click.prevent.stop="filterBy('sectionIds', section.id)"
							class="pkpListPanel__filterLabel"
							:class="{'--isActive': isFilterActive('sectionIds', section.id)}"
							:tabindex="tabIndex"
						>{{ section.title }}</a>
						<button
							v-if="isFilterActive('sectionIds', section.id)"
							href="#"
							class="pkpListPanel__filterRemove"
							@click.prevent.stop="clearFilter('sectionIds', section.id)"
						>
							<span class="fa fa-times-circle-o"></span>
							<span class="pkpListPanel__filterRemoveLabel">{{ __('filterRemove', {filterTitle: section.title}) }}</span>
						</button>
					</li>
				</ul>
			</div>
			<div class="pkpListPanel__filterSet">
				<ul>
					<li>
						<a href="#"
							@click.prevent.stop="filterByIncomplete()"
							class="pkpListPanel__filterLabel"
							:class="{'--isActive': isFilterActive('isIncomplete', true)}"
							:tabindex="tabIndex"
						>{{ i18n.incomplete }}</a>
						<button
							v-if="isFilterActive('isIncomplete', true)"
							href="#"
							class="pkpListPanel__filterRemove"
							@click.prevent.stop="clearFilters()"
						>
							<span class="fa fa-times-circle-o"></span>
							<span class="pkpListPanel__filterRemoveLabel">{{ __('filterRemove', {filterTitle: i18n.incomplete}) }}</span>
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
	props: ['isVisible', 'stages', 'sections', 'i18n'],
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
			// Deactivate the isIncomplete filter when anotherr filter is selected
			if (this.isFilterActive('isIncomplete', true)) {
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
		 * Remove a filter
		 */
		clearFilter: function(type, val) {
			this.activeFilters = this.activeFilters.filter(filter => {
				return filter.type !== type || filter.val !== val;
			});
			this.filterList(this.compileFilterParams());
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
			this.filterBy('isIncomplete', true);
		},
	},
};
</script>
