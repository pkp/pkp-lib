<template>
	<li>
		<strong>{{ title }}</strong>,
		<span v-if="author">{{ author }}</span>,
		<span v-if="dateSubmitted">{{ dateSubmitted }}</span>
		<a href="#">{{ i18n.view }}</a>
	</li>
</template>

<script>
export default {
	name: 'ListPanelItem',
	props: ['title', 'author', 'dateSubmitted', 'i18n'],
}
</script>
