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
import * as vue from 'vue';

import {createPinia} from 'pinia';
import GlobalMixins from '@/mixins/global.js';
import VueAnnouncer from '@vue-a11y/announcer';
import FloatingVue from 'floating-vue';

import PrimeVue from 'primevue/config';

import VueScrollTo from 'vue-scrollto';
import emitter from 'tiny-emitter/instance';

// Mixins exposed for plugins
import dialog from '@/mixins/dialog.js';
import localizeMoment from '@/mixins/localizeMoment.js';

// Global components of UI Library
import Badge from '@/components/Badge/Badge.vue';
import Dropdown from '@/components/Dropdown/Dropdown.vue';
import DropdownActions from '@/components/DropdownActions/DropdownActions.vue';
import Icon from '@/components/Icon/Icon.vue';
import SideNav from '@/components/SideNav/SideNav.vue';
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
import TableColumn from '@/components/Table/TableColumn.vue';
import TableHeader from '@/components/Table/TableHeader.vue';
import TableBody from '@/components/Table/TableBody.vue';
import TableRow from '@/components/Table/TableRow.vue';
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
import FieldOrcid from '@/components/Form/fields/FieldOrcid.vue';
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
import FieldSlider from '@/components/Form/fields/FieldSlider.vue';

// Panel components from UI Library
import ListPanel from '@/components/ListPanel/ListPanel.vue';

// Helper for initializing and tracking Vue controllers
import VueRegistry from './classes/VueRegistry.js';

// Register global components
VueRegistry.registerComponent('Badge', Badge);
VueRegistry.registerComponent('PkpBadge', Badge);
VueRegistry.registerComponent('Dropdown', Dropdown);
VueRegistry.registerComponent('PkpDropdown', Dropdown);
VueRegistry.registerComponent('DropdownActions', DropdownActions);
VueRegistry.registerComponent('Icon', Icon);
VueRegistry.registerComponent('PkpIcon', Icon);
VueRegistry.registerComponent('PkpSideNav', SideNav);
VueRegistry.registerComponent('Notification', Notification);
VueRegistry.registerComponent('PkpNotification', Notification);
VueRegistry.registerComponent('Panel', Panel);
VueRegistry.registerComponent('PkpPanel', Panel);
VueRegistry.registerComponent('PanelSection', PanelSection);
VueRegistry.registerComponent('PkpPanelSection', PanelSection);
VueRegistry.registerComponent('PkpButton', PkpButton);
VueRegistry.registerComponent('PkpHeader', PkpHeader);
VueRegistry.registerComponent('Spinner', Spinner);
VueRegistry.registerComponent('PkpSpinner', Spinner);
VueRegistry.registerComponent('Step', Step);
VueRegistry.registerComponent('PkpStep', Step);
VueRegistry.registerComponent('Steps', Steps);
VueRegistry.registerComponent('PkpSteps', Steps);
VueRegistry.registerComponent('Tab', Tab);
VueRegistry.registerComponent('PkpTab', Tab);
VueRegistry.registerComponent('Tabs', Tabs);
VueRegistry.registerComponent('PkpTabs', Tabs);

// Register other components
VueRegistry.registerComponent('PkpActionPanel', ActionPanel);
VueRegistry.registerComponent('PkpButtonRow', ButtonRow);
VueRegistry.registerComponent('PkpDoughnutChart', DoughnutChart);
VueRegistry.registerComponent('PkpLineChart', LineChart);
VueRegistry.registerComponent('PkpComposer', Composer);
VueRegistry.registerComponent('PkpDateRange', DateRange);
VueRegistry.registerComponent('PkpFile', File);
VueRegistry.registerComponent('PkpFileAttacher', FileAttacher);
VueRegistry.registerComponent('PkpFileUploader', FileUploader);
VueRegistry.registerComponent('PkpFileUploadProgress', FileUploadProgress);
VueRegistry.registerComponent('PkpFilter', PkpFilter);
VueRegistry.registerComponent('PkpFilterAutosuggest', FilterAutosuggest);
VueRegistry.registerComponent('PkpFilterSlider', FilterSlider);
VueRegistry.registerComponent(
	'PkpFilterSliderMultirange',
	FilterSliderMultirange,
);
VueRegistry.registerComponent('PkpList', List);
VueRegistry.registerComponent('PkpListItem', ListItem);
VueRegistry.registerComponent('PkpModal', Modal);
VueRegistry.registerComponent('PkpMultilingualProgress', MultilingualProgress);
VueRegistry.registerComponent('PkpOrderer', Orderer);
VueRegistry.registerComponent('PkpPagination', Pagination);
VueRegistry.registerComponent('PkpProgressBar', ProgressBar);
VueRegistry.registerComponent('PkpSearch', Search);
VueRegistry.registerComponent('PkpTable', Table);
VueRegistry.registerComponent('PkpTableBody', TableBody);
VueRegistry.registerComponent('PkpTableCell', TableCell);
VueRegistry.registerComponent('PkpTableColumn', TableColumn);
VueRegistry.registerComponent('PkpTableHeader', TableHeader);
VueRegistry.registerComponent('PkpTableRow', TableRow);
VueRegistry.registerComponent('PkpTooltip', Tooltip);

// Register Form components
VueRegistry.registerComponent('PkpForm', Form);
VueRegistry.registerComponent('PkpFieldArchivingPn', FieldArchivingPn);
VueRegistry.registerComponent(
	'PkpFieldAutosuggestPreset',
	FieldAutosuggestPreset,
);
VueRegistry.registerComponent('PkpFieldBase', FieldBase);
VueRegistry.registerComponent('PkpFieldBaseAutosuggest', FieldBaseAutosuggest);
VueRegistry.registerComponent('PkpFieldColor', FieldColor);
VueRegistry.registerComponent('PkpFieldControlledVocab', FieldControlledVocab);
VueRegistry.registerComponent('PkpFieldHtml', FieldHtml);
VueRegistry.registerComponent('PkpFieldOrcid', FieldOrcid);
VueRegistry.registerComponent('PkpFieldMetadataSetting', FieldMetadataSetting);
VueRegistry.registerComponent('PkpFieldOptions', FieldOptions);
VueRegistry.registerComponent('PkpFieldPreparedContent', FieldPreparedContent);
VueRegistry.registerComponent('PkpFieldPubId', FieldPubId);
VueRegistry.registerComponent('PkpFieldRadioInput', FieldRadioInput);
VueRegistry.registerComponent('PkpFieldRichText', FieldRichText);
VueRegistry.registerComponent('PkpFieldRichTextarea', FieldRichTextarea);
VueRegistry.registerComponent('PkpFieldSelect', FieldSelect);
VueRegistry.registerComponent('PkpFieldSelectIssue', FieldSelectIssue);
VueRegistry.registerComponent('PkpFieldSelectIssues', FieldSelectIssues);
VueRegistry.registerComponent(
	'PkpFieldSelectSubmissions',
	FieldSelectSubmissions,
);
VueRegistry.registerComponent('PkpFieldSelectUsers', FieldSelectUsers);
VueRegistry.registerComponent(
	'PkpFieldShowEnsuringLink',
	FieldShowEnsuringLink,
);
VueRegistry.registerComponent('PkpFieldText', FieldText);
VueRegistry.registerComponent('PkpFieldTextarea', FieldTextarea);
VueRegistry.registerComponent('PkpFieldUpload', FieldUpload);
VueRegistry.registerComponent('PkpFieldUploadImage', FieldUploadImage);
VueRegistry.registerComponent('PkpFieldSlider', FieldSlider);

// Required by the URN plugin, to be migrated at some point to pkp prefix
VueRegistry.registerComponent('field-text', FieldText);
VueRegistry.registerComponent('field-pub-id', FieldPubId);

// Register ListPanel
VueRegistry.registerComponent('PkpListPanel', ListPanel);

const pinia = createPinia();

function pkpCreateVueApp(createAppArgs) {
	// Initialize Vue
	const vueApp = createApp(createAppArgs);
	vueApp.use(pinia);
	vueApp.use(PrimeVue, {
		unstyled: true,
	});

	// https://github.com/vuejs/pinia/discussions/1197
	// to be able globally share stores
	vueApp.config.globalProperties.$store = {};

	// For compatibility with vue2 to preserve spaces between html tags
	vueApp.config.compilerOptions.whitespace = 'preserve';
	vueApp.use(VueScrollTo);
	vueApp.use(VueAnnouncer);
	vueApp.use(FloatingVue, {
		themes: {
			'pkp-tooltip': {
				$extend: 'tooltip',
				triggers: ['hover', 'focus'],
				delay: {
					show: 0,
					hide: 0,
				},
			},
		},
	});

	vueApp.mixin(GlobalMixins);

	// register all global components
	const allGlobalComponents = VueRegistry.getAllComponents();
	Object.keys(allGlobalComponents).forEach((componentName) => {
		vueApp.component(componentName, allGlobalComponents[componentName]);
	});

	return vueApp;
}

export default {
	Vue: pkpCreateVueApp({}),
	// making vue functions available via pkp.modules.vue for plugins
	// especially useful when using composition api
	modules: {
		vue,
	},
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
