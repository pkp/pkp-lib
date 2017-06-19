<template>
	<div href="#" @click.prevent="toggleFilter" class="pkpListPanel__filter" :class="{'--isVisible': this.isVisible}">
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
