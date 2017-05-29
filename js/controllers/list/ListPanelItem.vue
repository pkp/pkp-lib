<template>
	<li class="pkpListPanelItem" :class="{'--hasFocus': isFocused}">
		<div class="pkpListPanelItem--submission__item"></div>
	</li>
</template>

<script>
export default {
	name: 'ListPanelItem',
	data: function() {
		return {
			isFocused: false,
		}
	},
	computed: {
		/**
		 * Each item needs an id for list ordering to work. Most components
		 * should overwrite this computed property and map the item's id to
		 * this property.
		 */
		id: function() {
			return 0;
		},
	},
	methods: {

		/**
		 * Update the isFocused property
		 */
		focusItem: function() {
			this.isFocused = true;
		},

		/**
		 * Update the isFocused property
		 */
		blurItem: function() {
			this.isFocused = false;
		},

		/**
		 * Pass an itemOrderUp event up to the list panel
		 *
		 * This event emerges from a ListPanelItemOrderer component.
		 */
		itemOrderUp: function() {
			this.$emit('itemOrderUp', this.submission);
		},

		/**
		 * Pass an itemOrderDown event up to the list panel
		 *
		 * This event emerges from a ListPanelItemOrderer component.
		 */
		itemOrderDown: function() {
			this.$emit('itemOrderDown', this.submission);
		},
	},
};
</script>
