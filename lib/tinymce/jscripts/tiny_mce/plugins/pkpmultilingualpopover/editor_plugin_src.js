/**
 * editor_plugin_src.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 */

(function() {
	tinymce.create('tinymce.plugins.PKPMultilingualPopover', {
		init : function(ed, url) {
			var t = this;
			if(top.$.pkp.controllers.form.MultilingualInputHandler.receiveEditorEvent) {
				ed.onInit.add(function(ed) { t.blurEvent(ed); });

				ed.onEvent.add(function(ed, event) {
					top.$.pkp.controllers.form.MultilingualInputHandler.receiveEditorEvent(ed.editorId, event);
				});
			}
		},

		blurEvent : function(ed) {
			tinyMCE.dom.Event.add(ed.getWin(), "blur", function(event) {
					top.$.pkp.controllers.form.MultilingualInputHandler.receiveEditorEvent(ed.editorId, event);
				});
			},

		getInfo : function() {
			return {
				longname : 'PKPMultilingualPopover',
				author : 'Public Knowledge Project',
				authorurl : 'http://pkp.sfu.ca',
				infourl : 'http://pkp.sfu.ca',
				version : tinymce.majorVersion + "." + tinymce.minorVersion
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('pkpmultilingualpopover', tinymce.plugins.PKPMultilingualPopover);
})();
