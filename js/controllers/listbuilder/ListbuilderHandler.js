/**
 * @defgroup js_controllers_listbuilder
 */
// Define the namespace.
$.pkp.controllers.listbuilder = $.pkp.controllers.listbuilder || {};


/**
 * @file js/controllers/listbuilder/ListbuilderHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ListbuilderHandler
 * @ingroup js_controllers_listbuilder
 *
 * @brief Listbuilder row handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.grid.GridHandler
	 *
	 * @param {jQuery} $listbuilder The listbuilder this handler is
	 *  attached to.
	 * @param {Object} options Listbuilder handler configuration.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler =
			function($listbuilder, options) {
		this.parent($listbuilder, options);

		// Save listbuilder options
		this.sourceType_ = options.sourceType;
		this.saveUrl_ = options.saveUrl;
		this.saveFieldName_ = options.saveFieldName;
		this.fetchOptionsUrl_ = options.fetchOptionsUrl;

		// Attach the button handlers
		$listbuilder.find('.add_item').click(
				this.callbackWrapper(this.addItemHandler_));

		// Attach the content manipulation handlers
		this.attachContentHandlers_($listbuilder);

		// Sign up for notification of form submission
		this.bind('formSubmitRequested', this.formSubmitHandler_);
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.listbuilder.ListbuilderHandler,
			$.pkp.controllers.grid.GridHandler);


	//
	// Private properties
	//
	/**
	 * The source type (LISTBUILDER_SOURCE_TYPE_...) of the listbuilder.
	 * @private
	 * @type {?number}
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			sourceType_ = null;


	/**
	 * The "save" URL of the listbuilder (for
	 * LISTBUILDER_SAVE_TYPE_INTERNAL).
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			saveUrl_ = null;


	/**
	 * The "save" field name of the listbuilder (for
	 * LISTBUILDER_SAVE_TYPE_EXTERNAL).
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			saveFieldName_ = null;


	/**
	 * The "fetch options" URL of the listbuilder (for "select" source type).
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			fetchOptionsUrl_ = null;


	//
	// Protected methods
	//
	/**
	 * Get the "save" URL for LISTBUILDER_SAVE_TYPE_INTERNAL.
	 * @private
	 * @return {?string} URL to the "save listbuilder" handler operation.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.getSaveUrl_ =
			function() {

		return this.saveUrl_;
	};


	/**
	 * Get the "save" field name for LISTBUILDER_SAVE_TYPE_EXTERNAL.
	 * @private
	 * @return {?string} Name of the field to transmit LB contents in.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.getSaveFieldName_ =
			function() {

		return this.saveFieldName_;
	};


	/**
	 * "Save" and close any editing rows in the listbuilder.
	 * @protected
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.closeEdits =
			function() {

		var $editedRow = this.getHtmlElement().find('.gridRowEdit:visible');
		if ($editedRow.length !== 0) {
			this.saveRow($editedRow);
			$editedRow.removeClass('gridRowEdit');
		}
	};


	/**
	 * Save the listbuilder.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.save =
			function() {

		// Get deletions
		var deletions = this.getHtmlElement().find('input.deletions').val();

		// Get insertions and modifications
		var changes = [];
		this.getHtmlElement().find('.gridRow input.isModified[value="1"]')
				.each(this.callbackWrapper(function(context, k, v) {
					var $row = $(v).parents('.gridRow');
					var params = this.buildParamsFromInputs_($row.find(':input'));
					changes.push(params);
				}));

		// Assemble and send to the server
		var stringifiedData = JSON.stringify(
				{deletions: deletions, changes: changes});
		var saveUrl = this.getSaveUrl_();
		if (saveUrl) {
			// Post the changes to the server using the internal
			// save handler.
			$.post(saveUrl, {data: stringifiedData},
					this.callbackWrapper(this.saveResponseHandler_, null), 'json');
		} else {
			// Supply the data to an external save handler (e.g.
			// a form handler) using a hidden field.
			var saveFieldName = this.getSaveFieldName_();

			// Try to find and reuse an existing element (if
			// e.g. a previous attempt was aborted)
			var $e = this.getHtmlElement()
					.find(':input[type=hidden]')
					.filter(
					function() {return $(this).attr('name') == saveFieldName;})
					.first();

			// If we couldn't find one, create one.
			if ($e.length === 0) {
				$e = $('<input type="hidden" />');
				$e.attr('name', saveFieldName);
				this.getHtmlElement().append($e);
			}

			// Set the value of the hidden element.
			$e.attr('value', stringifiedData);
		}
	};


	/**
	 * Function that will be called to save an edited row.
	 * @param {Object} $row The DOM element representing the row to save.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			saveRow = function($row) {

		// Retrieve a single new row from the server.
		// (Avoid IE closure leak using this flag rather than passing
		// around a DOM element in a closure.)
		$row.addClass('saveRowResponsePlaceholder');
		var params = this.buildParamsFromInputs_($row.find(':input'));
		params.modify = true; // Flag the row for modification
		// Use a blocking request to avoid race conditions sometimes
		// duplicating items, i.e. when editing an existing item after
		// adding a new one.
		$.ajax({
			url: this.getFetchRowUrl(),
			data: params,
			success: this.callbackWrapper(this.saveRowResponseHandler_, null),
			dataType: 'json',
			async: false
		});
	};


	//
	// Private Methods
	//
	/**
	 * Callback that will be activated when the "add item" icon is clicked
	 *
	 * @private
	 *
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} event The triggering event (e.g. a click on
	 *  a button.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.addItemHandler_ =
			function(callingContext, event) {

		// Close any existing edits if necessary
		this.closeEdits();

		$.get(this.getFetchRowUrl(), {modify: true},
				this.callbackWrapper(this.appendRowResponseHandler_, null), 'json');

		return false;
	};


	/**
	 * Callback that will be activated when a delete icon is clicked
	 *
	 * @private
	 *
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} event The triggering event (e.g. a click on
	 *  a button.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.deleteItemHandler_ =
			function(callingContext, event) {

		// Close any existing edits if necessary
		this.closeEdits();

		var $callingContext = $(callingContext);
		var $targetRow = $callingContext.closest('.gridRow');
		var $deletions = $callingContext.closest('.pkp_controllers_listbuilder')
				.find('.deletions');
		var rowId = $targetRow.find('input[name="rowId"]').val();

		// Append the row ID to the deletions list.
		if (rowId !== undefined) {
			$deletions.val($deletions.val() + ' ' + rowId);
		}

		// Hide and delete the item.
		$targetRow.hide('slow', function() {
			$(this).remove();
		});

		return false;
	};


	/**
	 * Callback that will be activated when a request for row appending
	 * returns.
	 *
	 * @private
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			appendRowResponseHandler_ = function(ajaxContext, jsonData) {

		jsonData = this.handleJson(jsonData);
		if (jsonData !== false) {
			// Show the new input row; hide the "empty" row
			var $newRow = $(jsonData.content);
			this.getHtmlElement().find('.empty').hide().before($newRow);

			// Attach content handlers and focus
			this.attachContentHandlers_($newRow);
			$newRow.addClass('gridRowEdit');
			$newRow.find(':input').first().focus();

			// If this is a select menu listbuilder, load the options
			if (this.sourceType_ == $.pkp.cons.LISTBUILDER_SOURCE_TYPE_SELECT) {
				$.get(this.fetchOptionsUrl_, {},
						this.callbackWrapper(this.fetchOptionsResponseHandler_, null),
							'json');
			}
		}

		return false;
	};


	/**
	 * Callback that will be activated when a request for row appending
	 * returns.
	 *
	 * @private
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			fetchOptionsResponseHandler_ = function(ajaxContext, jsonData) {

		// Find the currently editable select menu and fill
		jsonData = this.handleJson(jsonData);
		if (jsonData !== false) {
			var $listbuilder = this.getHtmlElement();

			// Get the list of already-selected options, to ensure
			// that we don't offer duplicates.
			var selectedValues = [];
			$listbuilder.find('.gridCellDisplay :input').each(function(i, selected) {
				selectedValues[i] = $(selected).val();
			});

			// Get the currently available input row's elements
			var $selectInput = $listbuilder.find(
					'.gridRowEdit:visible .selectMenu:input'
					);

			// For each pulldown (generally 1), add options.
			$selectInput.each(function(i) {
				// Fetch some useful properties
				var $this = $(this);
				var $container = $this.parents('.gridCellContainer');
				var currentIndex = $container.find('.gridCellDisplay :input').val();

				// Add the options, noting the currently selected index
				var options = '';
				var optionsCount = 0;
				var selectedIndex = null;
				$this.children().empty();
				var $lastElement;
				var j = null;
				for (j in jsonData.content[i]) {
					// Check to see if this option is
					// already in the LB.
					var isDuplicate = false;
					if (j != currentIndex) {
						// If it's the current row, don't consider it a duplicate
						for (var k = 0; k < selectedValues.length; k++) {
							if (selectedValues[k] == j) {
								isDuplicate = true;
							}
						}
					}

					if (!isDuplicate) {
						// Create and populate the option node
						var content = jsonData.content[i][j];
						var $option = $('<option/>');
						$option.attr('value', j);
						$option.text(content);

						if (j == currentIndex) {
							$option.attr('selected', 'selected');
						}

						$this.append($option);
						optionsCount++;
						$lastElement = $option;
					}
				}

				// If only one element is available, select it.
				if (optionsCount === 1) {
					$lastElement.attr('selected', 'selected');
				}

				// If no options are available for this select menu,
				// hide the input to prevent empty dropdown.
				if (optionsCount === 0) {
					$container.find('.gridCellDisplay').show();
					$container.find('.gridCellEdit').hide();
				}
			});
		}

		return false;
	};


	/**
	 * Callback that will be activated when a row is clicked for editing
	 *
	 * @private
	 *
	 * @param {HTMLElement} callingContext The calling element or object.
	 * @param {Event=} event The triggering event (e.g. a click on
	 *  a button.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.editItemHandler_ =
			function(callingContext, event) {

		var $targetRow = $(callingContext).closest('.gridRow');

		// Close any existing edits if necessary
		this.closeEdits();

		// Show inputs; hide display
		$targetRow.addClass('gridRowEdit');
		$targetRow.find(':input').first().focus();

		// If this is a select menu listbuilder, load the options
		if (this.sourceType_ == $.pkp.cons.LISTBUILDER_SOURCE_TYPE_SELECT) {
			$.get(this.fetchOptionsUrl_, {},
					this.callbackWrapper(this.fetchOptionsResponseHandler_, null),
						'json');
		}

		return false;
	};


	/**
	 * Helper function to turn a row into an array of parameters used
	 * to generate the DOM representation of that row when bounced
	 * off the server.
	 *
	 * @private
	 *
	 * @param {Object} $inputs The grid inputs to mine for parameters.
	 * @return {Object} A name: value association of relevant parameters.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			buildParamsFromInputs_ = function($inputs) {

		var params = {};
		$.each($inputs.serializeArray(), function(k, v) {
			var name = v.name;
			var value = v.value;

			params[name] = params[name] === undefined ? value :
					$.isArray(params[name]) ? params[name].concat(value) :
					[params[name], value];
		});

		return params;
	};


	/**
	 * Callback that will be activated upon keystroke in a new input field
	 * to check for a <cr> (acts as tab-to-next, or if no next, submit).
	 *
	 * @private
	 *
	 * @param {HTMLElement} callingContext The calling element or object.
	 * @param {Event=} event The triggering event.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			inputKeystrokeHandler_ = function(callingContext, event) {

		var CR_KEY = 13;
		var TAB_KEY = 9;

		if (event.which == CR_KEY) {
			var $target = $(callingContext);
			var $row = $target.parents('.gridRow');
			var $inputs = $row.find(':input:visible');
			var i = $inputs.index($target);
			if ($inputs.length == i + 1) {
				this.saveRow($row);
				return false; // Prevent default
			} else {
				// Not the last field. Tab to the next.
				$inputs[i + 1].focus();
				return false; // Prevent default
			}
		}
		return true;
	};


	/**
	 * Callback that will be activated when a new/modifying row's input
	 * field is blurred to check whether or not to save the row.
	 *
	 * @private
	 *
	 * @param {HTMLElement} callingContext The calling element or object.
	 * @param {Event=} event The triggering event.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			inputBlurHandler_ = function(callingContext, event) {

		// Flag currently selected input using a CSS class. (Don't
		// want to pass it into the closure because of the IE memory
		// leak bug.)
		$(callingContext).closest('.gridRow').addClass('editingRowPlaceholder');

		// Check to see whether the row has lost focus after this event has
		// been processed.
		setTimeout(this.callbackWrapper(function() {
			var $editingRow = $('.editingRowPlaceholder');
			var found = false;
			$editingRow.find(':input').each(function(index, elem) {
				if (elem === document.activeElement) {
					found = true;
				}
			});

			// Clean up extra placeholder class.
			$editingRow.removeClass('editingRowPlaceholder');

			// If the focused element isn't within the current row, save.
			if (!found) {
				this.closeEdits();
			}
		}), 0);

		return true;
	};


	/**
	 * Callback to replace a grid row's content.
	 *
	 * @private
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			saveRowResponseHandler_ = function(ajaxContext, jsonData) {

		jsonData = this.handleJson(jsonData);
		if (jsonData !== false) {
			// Unfortunately we can't use a closure to get this from
			// the calling context. Use a class flag "saveRowResponsePlaceholder".
			// (Risks IE closure/DOM element memory leak.)
			var $newContent = $(jsonData.content);

			// Add to the DOM
			this.getHtmlElement().find('.saveRowResponsePlaceholder').
					replaceWith($newContent);

			// Attach handlers for content manipulation
			this.attachContentHandlers_($newContent);
		}
	};


	/**
	 * Callback after a save response returns from the server.
	 *
	 * @private
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			saveResponseHandler_ = function(ajaxContext, jsonData) {

		// Noop
	};


	/**
	 * Attach content handlers to all "click-to-edit" content within
	 * the provided context.
	 *
	 * @private
	 *
	 * @param {Object} $context The JQuery object to search for attachables.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.prototype.
			attachContentHandlers_ = function($context) {

		// Attach click handler for text fields and select menus
		$context.find('.gridCellDisplay').click(
				this.callbackWrapper(this.editItemHandler_));

		// Attach keypress handler for text fields
		$context.find(':input')
				.keypress(this.callbackWrapper(this.inputKeystrokeHandler_))
				.blur(this.callbackWrapper(this.inputBlurHandler_));

		// Attach deletion handler
		$context.find('.remove_item').click(
				this.callbackWrapper(this.deleteItemHandler_));
	};


	/**
	 * Save the Listbuilder's contents upon a "form submitted" event.
	 * @private
	 *
	 * @param {$.pkp.controllers.form.AjaxFormHandler} callingForm The form
	 *  that triggered the event.
	 * @param {Event} event The event.
	 * @return {boolean} False iff the form submission should abort.
	 */
	$.pkp.controllers.listbuilder.ListbuilderHandler.
			prototype.formSubmitHandler_ = function(callingForm, event) {

		// Save the contents
		this.save();

		// Prevent the submission of LB elements to the parent form
		// (except potentially for :input[name='getSaveFieldName()'])
		this.getHtmlElement().find('.gridRow :input').attr('disabled', 'disabled');

		// Continue the default (form submit) behavior
		return true;
	};
/** @param {jQuery} $ jQuery closure. */
})(jQuery);
