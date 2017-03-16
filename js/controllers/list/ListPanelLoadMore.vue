<template>
	<div class="pkpListPanel__loadMore" :class="classLoadingMore">
		<a href="#" class="pkpListPanel__loadMoreButton" @click="loadMore">
			<span class="fa fa-chevron-down"></span>
			{{ i18n.loadMore }}
		</a>
		<span class="pkpListPanel__loadMoreNotice">
			<span class="pkp_spinner"></span>
			{{ i18n.loading }}
		</span>
	</div>
</template>

<script>
export default {
	name: 'ListPanelLoadMore',
	props: ['isLoading', 'classLoading', 'i18n'],
	computed: {
		classLoadingMore: function() {
			return { '--isLoadingMore': this.isLoading };
		},
	},
	methods: {
		/**
		 * Emit an event to load more items into this list panel
		 */
		loadMore: function(e) {

			if (e instanceof Event) {
				e.preventDefault();
			}

			this.$emit('loadMore');
		}
	}
}
</script>
