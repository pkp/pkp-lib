/**
 * @file js/load.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Common configuration for building the Javascript package
 */

// Vue lib and custom mixins
import {createApp} from 'vue';
import GlobalMixins from '@/mixins/global.js';
import VueAnnouncer from '@vue-a11y/announcer';
import FloatingVue from 'floating-vue';

import VueScrollTo from 'vue-scrollto';
import emitter from 'tiny-emitter/instance';

// Mixins exposed for plugins
import dialog from '@/mixins/dialog.js';
import localizeMoment from '@/mixins/localizeMoment.js';

// Global components of UI Library
import Badge from '@/components/Badge/Badge.vue';
import Dropdown from '@/components/Dropdown/Dropdown.vue';
import Icon from '@/components/Icon/Icon.vue';
import Notification from '@/components/Notification/Notification.vue';
import Panel from '@/components/Panel/Panel.vue';
import PanelSection from '@/components/Panel/PanelSection.vue';
import PkpButton from '@/components/Button/Button.vue';
import PkpHeader from '@/components/Header/Header.vue';
import Spinner from '@/components/Spinner/Spinner.vue';
import Step from '@/components/Steps/Step.vue';
import Steps from '@/components/Steps/Steps.vue';
import Tab from '@/components/Tabs/Tab.vue';
import Tabs from '@/components/Tabs/Tabs.vue';

// Other components of UI Library
import ActionPanel from '@/components/ActionPanel/ActionPanel.vue';
import ButtonRow from '@/components/ButtonRow/ButtonRow.vue';
import DoughnutChart from '@/components/Chart/DoughnutChart.vue';
import LineChart from '@/components/Chart/LineChart.vue';
import Composer from '@/components/Composer/Composer.vue';
import DateRange from '@/components/DateRange/DateRange.vue';
import File from '@/components/File/File.vue';
import FileAttacher from '@/components/FileAttacher/FileAttacher.vue';
import FileUploader from '@/components/FileUploader/FileUploader.vue';
import FileUploadProgress from '@/components/FileUploadProgress/FileUploadProgress.vue';
import PkpFilter from '@/components/Filter/Filter.vue';
import FilterAutosuggest from '@/components/Filter/FilterAutosuggest.vue';
import FilterSlider from '@/components/Filter/FilterSlider.vue';
import FilterSliderMultirange from '@/components/Filter/FilterSliderMultirange.vue';
import List from '@/components/List/List.vue';
import ListItem from '@/components/List/ListItem.vue';
import Modal from '@/components/Modal/Modal.vue';
import MultilingualProgress from '@/components/MultilingualProgress/MultilingualProgress.vue';
import Orderer from '@/components/Orderer/Orderer.vue';
import Pagination from '@/components/Pagination/Pagination.vue';
import ProgressBar from '@/components/ProgressBar/ProgressBar.vue';
import Search from '@/components/Search/Search.vue';
import Table from '@/components/Table/Table.vue';
import TableCell from '@/components/Table/TableCell.vue';
import Tooltip from '@/components/Tooltip/Tooltip.vue';

// Form components from UI Library
import Form from '@/components/Form/Form.vue';
import FieldArchivingPn from '@/components/Form/fields/FieldArchivingPn.vue';
import FieldAutosuggestPreset from '@/components/Form/fields/FieldAutosuggestPreset.vue';
import FieldBase from '@/components/Form/fields/FieldBase.vue';
import FieldBaseAutosuggest from '@/components/Form/fields/FieldBaseAutosuggest.vue';
import FieldColor from '@/components/Form/fields/FieldColor.vue';
import FieldControlledVocab from '@/components/Form/fields/FieldControlledVocab.vue';
import FieldHtml from '@/components/Form/fields/FieldHtml.vue';
import FieldMetadataSetting from '@/components/Form/fields/FieldMetadataSetting.vue';
import FieldOptions from '@/components/Form/fields/FieldOptions.vue';
import FieldPreparedContent from '@/components/Form/fields/FieldPreparedContent.vue';
import FieldPubId from '@/components/Form/fields/FieldPubId.vue';
import FieldRadioInput from '@/components/Form/fields/FieldRadioInput.vue';
import FieldRichText from '@/components/Form/fields/FieldRichText.vue';
import FieldRichTextarea from '@/components/Form/fields/FieldRichTextarea.vue';
import FieldSelect from '@/components/Form/fields/FieldSelect.vue';
import FieldSelectIssue from '@/components/Form/fields/FieldSelectIssue.vue';
import FieldSelectIssues from '@/components/Form/fields/FieldSelectIssues.vue';
import FieldSelectSubmissions from '@/components/Form/fields/FieldSelectSubmissions.vue';
import FieldSelectUsers from '@/components/Form/fields/FieldSelectUsers.vue';
import FieldShowEnsuringLink from '@/components/Form/fields/FieldShowEnsuringLink.vue';
import FieldText from '@/components/Form/fields/FieldText.vue';
import FieldTextarea from '@/components/Form/fields/FieldTextarea.vue';
import FieldUpload from '@/components/Form/fields/FieldUpload.vue';
import FieldUploadImage from '@/components/Form/fields/FieldUploadImage.vue';

// Panel components from UI Library
import ListPanel from '@/components/ListPanel/ListPanel.vue';

// Helper for initializing and tracking Vue controllers
import VueRegistry from './classes/VueRegistry.js';

function pkpCreateVueApp(createAppArgs) {
	// Initialize Vue
	const vueApp = createApp(createAppArgs);

	// For compatibility with vue2 to preserve spaces between html tags
	vueApp.config.compilerOptions.whitespace = 'preserve';
	vueApp.use(VueScrollTo);
	vueApp.use(VueAnnouncer);
	vueApp.use(FloatingVue, {
		themes: {
			'pkp-tooltip': {
				$extend: 'tooltip',
				triggers: ['click'],
				delay: {
					show: 0,
					hide: 0,
				},
			},
		},
	});

	vueApp.mixin(GlobalMixins);

	// Register global components
	vueApp.component('Badge', Badge);
	vueApp.component('PkpBadge', Badge);
	vueApp.component('Dropdown', Dropdown);
	vueApp.component('PkpDropdown', Dropdown);
	vueApp.component('Icon', Icon);
	vueApp.component('PkpIcon', Icon);
	vueApp.component('Notification', Notification);
	vueApp.component('PkpNotification', Notification);
	vueApp.component('Panel', Panel);
	vueApp.component('PkpPanel', Panel);
	vueApp.component('PanelSection', PanelSection);
	vueApp.component('PkpPanelSection', PanelSection);
	vueApp.component('PkpButton', PkpButton);
	vueApp.component('PkpHeader', PkpHeader);
	vueApp.component('Spinner', Spinner);
	vueApp.component('PkpSpinner', Spinner);
	vueApp.component('Step', Step);
	vueApp.component('PkpStep', Step);
	vueApp.component('Steps', Steps);
	vueApp.component('PkpSteps', Steps);
	vueApp.component('Tab', Tab);
	vueApp.component('PkpTab', Tab);
	vueApp.component('Tabs', Tabs);
	vueApp.component('PkpTabs', Tabs);

	// Register other components
	vueApp.component('PkpActionPanel', ActionPanel);
	vueApp.component('PkpButtonRow', ButtonRow);
	vueApp.component('PkpDoughnutChart', DoughnutChart);
	vueApp.component('PkpLineChart', LineChart);
	vueApp.component('PkpComposer', Composer);
	vueApp.component('PkpDateRange', DateRange);
	vueApp.component('PkpFile', File);
	vueApp.component('PkpFileAttacher', FileAttacher);
	vueApp.component('PkpFileUploader', FileUploader);
	vueApp.component('PkpFileUploadProgress', FileUploadProgress);
	vueApp.component('PkpFilter', PkpFilter);
	vueApp.component('PkpFilterAutosuggest', FilterAutosuggest);
	vueApp.component('PkpFilterSlider', FilterSlider);
	vueApp.component('PkpFilterSliderMultirange', FilterSliderMultirange);
	vueApp.component('PkpList', List);
	vueApp.component('PkpListItem', ListItem);
	vueApp.component('PkpModal', Modal);
	vueApp.component('PkpMultilingualProgress', MultilingualProgress);
	vueApp.component('PkpOrderer', Orderer);
	vueApp.component('PkpPagination', Pagination);
	vueApp.component('PkpProgressBar', ProgressBar);
	vueApp.component('PkpSearch', Search);
	vueApp.component('PkpTable', Table);
	vueApp.component('PkpTableCell', TableCell);
	vueApp.component('PkpTooltip', Tooltip);

	// Register Form components
	vueApp.component('PkpForm', Form);
	vueApp.component('PkpFieldArchivingPn', FieldArchivingPn);
	vueApp.component('PkpFieldAutosuggestPreset', FieldAutosuggestPreset);
	vueApp.component('PkpFieldBase', FieldBase);
	vueApp.component('PkpFieldBaseAutosuggest', FieldBaseAutosuggest);
	vueApp.component('PkpFieldColor', FieldColor);
	vueApp.component('PkpFieldControlledVocab', FieldControlledVocab);
	vueApp.component('PkpFieldHtml', FieldHtml);
	vueApp.component('PkpFieldMetadataSetting', FieldMetadataSetting);
	vueApp.component('PkpFieldOptions', FieldOptions);
	vueApp.component('PkpFieldPreparedContent', FieldPreparedContent);
	vueApp.component('PkpFieldPubId', FieldPubId);
	vueApp.component('PkpFieldRadioInput', FieldRadioInput);
	vueApp.component('PkpFieldRichText', FieldRichText);
	vueApp.component('PkpFieldRichTextarea', FieldRichTextarea);
	vueApp.component('PkpFieldSelect', FieldSelect);
	vueApp.component('PkpFieldSelectIssue', FieldSelectIssue);
	vueApp.component('PkpFieldSelectIssues', FieldSelectIssues);
	vueApp.component('PkpFieldSelectSubmissions', FieldSelectSubmissions);
	vueApp.component('PkpFieldSelectUsers', FieldSelectUsers);
	vueApp.component('PkpFieldShowEnsuringLink', FieldShowEnsuringLink);
	vueApp.component('PkpFieldText', FieldText);
	vueApp.component('PkpFieldTextarea', FieldTextarea);
	vueApp.component('PkpFieldUpload', FieldUpload);
	vueApp.component('PkpFieldUploadImage', FieldUploadImage);

	// Required by the URN plugin, to be migrated at some point to pkp prefix
	vueApp.component('field-text', FieldText);
	vueApp.component('field-pub-id', FieldPubId);

	// Register ListPanel
	vueApp.component('PkpListPanel', ListPanel);

	return vueApp;
}

export default {
	Vue: pkpCreateVueApp({}),
	pkpCreateVueApp,
	createApp,
	registry: VueRegistry,
	eventBus: {
		$on: (...args) => emitter.on(...args),
		$once: (...args) => emitter.once(...args),
		$off: (...args) => emitter.off(...args),
		$emit: (...args) => emitter.emit(...args),
	},
	const: {},
	localeKeys: {},
	currentUser: null,
	vueMixins: {
		dialog,
		localizeMoment,
	},
};
