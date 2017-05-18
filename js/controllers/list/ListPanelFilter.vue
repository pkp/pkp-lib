<template>
	<a href="#" @click.prevent="toggleFilter" class="pkpListPanel__filter" :class="{'--isVisible': this.isVisible}">
		<div class="pkpListPanel__filterHeader">
			<span class="fa fa-filter"></span>
			{{ i18n.filter }}
		</div>
		<div class="pkpListPanel__filterOptions"></div>
	</a>
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
	methods: {
		/**
		 * Emit an event to filter items in the list panel
		 */
		filterList: function(data) {
			this.$emit('filterList', data);
		},

		/**
		 * Clear any filters that are currently active
		 */
		clearFilters: function() {
			this.activeFilters = [];
			this.filterList({});
		},
	},
}
</script>
