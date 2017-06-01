<template>
	<div class="pkpListPanel__search" :class="classSearching">
		<label>
			<span class="pkpListPanel__searchLabel">{{ i18n.search }}</span>
			<input type="search"
				@keyup="searchPhraseChanged"
				:value="searchPhrase"
				:placeholder="i18n.search"
			>
			<span class="pkpListPanel__searchIcons">
				<span class="pkpListPanel__searchIcons--search"></span>
				<span class="pkpListPanel__searchIcons--searching"></span>
			</span>
		</label>
	</div>
</template>

<script>
import ListPanelCount from './ListPanelCount.vue';

export default {
	name: 'ListPanelSearch',
	props: ['isSearching', 'searchPhrase', 'i18n'],
	computed: {
        classSearching: function() {
            return { searching: this.isSearching };
        },
	},
	methods: {
        /**
         * A throttled function to signal to the parent element that the
		 * searchPhrase should be updated. It's throttled to allow it to be
		 * fired by frequent events, like keyup.
		 *
		 * @param string|object data A DOM event (object) or the new search
		 *  phrase (string)
         */
		searchPhraseChanged: _.debounce(function(data) {
			var newVal = typeof data === 'String' ? data : data.target.value;
			this.$emit('searchPhraseChanged', newVal);
		}, 250),
	},
}
</script>
