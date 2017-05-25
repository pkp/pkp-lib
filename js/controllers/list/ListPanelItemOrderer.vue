<template>
	<div class="pkpListPanelItem__orderer">
		<button tabindex="-1" class="pkpListPanelItem__ordererDragDrop">
			<span class="fa fa-sort"></span>
		</button>
		<button class="pkpListPanelItem__ordererUp" @click.prevent="orderUp">
			<span class="fa fa-chevron-up"></span>
			<span class="pkp_screen_reader">Move {{ itemTitle }} up.</span>
		</button>
		<button class="pkpListPanelItem__ordererDown" @click.prevent="orderDown">
			<span class="fa fa-chevron-down"></span>
			<span class="pkp_screen_reader">Move {{ itemTitle }} down.</span>
		</button>
	</div>
</template>

<script>
export default {
	name: 'ListPanelItemOrderer',
	props: ['i18n', 'itemTitle'],
	methods: {
		/**
		 * Emit an event to move this item up
		 */
		orderUp: function() {
			this.$emit('itemOrderUp');
		},

		/**
		 * Emit an event to move this item down
		 */
		orderDown: function() {
			this.$emit('itemOrderDown');
		}
	}
}
</script>
