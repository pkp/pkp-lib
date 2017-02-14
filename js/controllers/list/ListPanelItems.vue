<template>
	<ul class="pkp-vue-list-panel-items">
		<list-panel-item
			v-for="item in items"
			v-bind:title="item.title"
			v-bind:author="item.author"
			v-bind:dateSubmitted="item.dateSubmitted"
			v-bind:i18n="i18n"
		/>
	</ul>
</template>

<script>
import ListPanelItem from './ListPanelItem.vue';

export default {
	name: 'ListPanelItems',
	components: {
		ListPanelItem,
	},
	props: ['items', 'i18n'],
}
</script>
