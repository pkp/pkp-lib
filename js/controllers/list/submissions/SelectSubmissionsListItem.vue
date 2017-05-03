<template>
	<li class="pkpListPanelItem pkpListPanelItem--submission pkpListPanelItem--select">
		<a href="#" @click.prevent="toggleSelection">
			<div class="pkpListPanelItem__selectItem">
				<input type="checkbox" :name="inputName" :value="inputValue" :checked="selected" @click.stop>
			</div>
		</a>
		<a :href="accessUrl">
			<div class="pkpListPanelItem--submission__item">
				<div class="pkpListPanelItem--submission__title">
					{{ submission.title }}
				</div>
				<div v-if="submission.author" class="pkpListPanelItem--submission__author">
					{{ submission.author.authorString }}
				</div>
			</div>
		</a>
</template>

<script>
import SubmissionsListItem from './SubmissionsListItem.vue';

export default _.extend({}, SubmissionsListItem, {
	name: 'SelectSubmissionsListItem',
	props: ['submission', 'i18n', 'inputName'],
	data: function() {
		return {
			selected: false,
		};
	},
	computed: {
		/**
		 * @see SubmissionsListItem.computed.accessUrl
		 */
		accessUrl: function() {
			return SubmissionsListItem.computed.accessUrl.call(this);
		},

		/**
		 * The input value to use for this item
		 *
		 * @return string
		 */
		inputValue: function() {
			return this.submission.id;
		},
	},
	methods: {
		/**
		 * Toggle the checkbox when clicked
		 */
		toggleSelection: function(e) {
			this.selected = !this.selected;
		}
	}
});
</script>
