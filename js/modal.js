/**
 * modal.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Implementation of jQuery modals for OMP.
 */

/**
 * modal
 * @param $url URL to load into the modal
 * @param $actType Type to define if callback should do (nothing|append|replace|remove)
 * @param $actOnId The ID on which to perform the action on callback
 * @param $apendTo Selector to append data to
 * @param $localizedButtons Array of translated 'Cancel/submit' strings
 * @param $callingButton Selector of the button that opens the modal
 * @param $dialogTitle Set a custom title for the dialog
 */
function modal(url, actType, actOnId, localizedButtons, callingButton, dialogTitle) {
	$(document).ready(function() {
		var validator = null;
		var title = dialogTitle ? dialogTitle : $(callingButton).text();
		var okButton = localizedButtons[0];
		var cancelButton = localizedButtons[1];
		var d = new Date();
		var UID = Math.ceil(1000 * Math.random(d.getTime()));
		var formContainer = '#' + UID;

		// Construct action to perform when OK and Cancels buttons are clicked
		var dialogOptions = {};
		if (actType == 'nothing') {
			// If the action type is 'nothing' then simply close the
			// dialog when the OK button is pressed. No cancel button
			// is needed.
			dialogOptions[okButton] = function() {
				$(this).dialog("close");
			};
		} else {
			// All other action types will assume that there is a
			// form to be posted and post it.
			dialogOptions[okButton] = function() {
				submitJsonForm(formContainer, actType, actOnId);
			};
			dialogOptions[cancelButton] = function() {
				$(this).dialog("close");
			};
		}

		// Tell the calling button to open this modal on click
		$(callingButton).die("click").live("click", (function() {
			// Construct dialog
			var $dialog = $('<div id=' + UID + '></div>').dialog({
				title: title,
				autoOpen: false,
				width: 700,
				modal: true,
				draggable: false,
				resizable: false,
				position: ['center', 100],
				buttons: dialogOptions,
				open: function(event, ui) {
			        $(this).css({'max-height': 600, 'overflow-y': 'auto', 'z-index': '10000'}); 
					$.getJSON(url, function(jsonData) {
						$('#loading').hide();
						if (jsonData.status === true) {
							$('#' + UID).html(jsonData.content);
						} else {
							// Alert that the modal failed
							alert(jsonData.content);
						}
					});
					$(this).html("<div id='loading' class='throbber'></div>");
					$('#loading').show();
				},
				close: function() {
					// Reset form validation errors and inputs on close
					if (validator != null) {
						validator.resetForm();
					}
					clearFormFields($(formContainer).find('form'));
				}
			});

			$dialog.dialog('open');
			return false;
		}));

	});
}

/**
 * Submit a form that returns JSON data.
 * @param formContainer String Container of form to be submitted, most likely a modal's ID (must include '#')
 * @param actType String Type to define if callback should do (nothing|append|replace|remove)
 * @param actOnId String The ID on which to perform the action on callback
 */
function submitJsonForm(formContainer, actType, actOnId) {
	// jQuerify the form container and find the form in it.
	$formContainer = $(formContainer);
	$form = $formContainer.find('form');
	validator = $form.validate();

	// Post to server and construct callback
	if ($form.valid()) {
		$.post(
			$form.attr('action'),
			$form.serialize(),
			function(jsonData) {
				if(jsonData.isScript == true) {
					eval(jsonData.script);
				}
				if (jsonData.status == true) {
					updateItem(actType, actOnId, jsonData.content);
					if (typeof($formContainer.dialog) == 'function') {
						$formContainer.dialog('close');
					}
					$formContainer.triggerHandler('submitSuccessful');
				} else {
					// If an error occurs then redisplay the form
					$formContainer.html(jsonData.content);
					$formContainer.triggerHandler('submitFailed');
				}
			},
			"json"
		);
		validator = null;
	}
}


/**
 * Opens a modal confirm box.
 * @param url String URL to load into the modal
 * @param actType String Type to define if callback should do (nothing|append|replace|remove)
 * @param actOnId String The ID on which to perform the action on callback
 * @param dialogText String Text to display in the dialog
 * @param localizedButtons Array of translated 'Cancel/submit' strings
 * @param callingButton String Selector of the button that opens the modal
 */
function modalConfirm(url, actType, actOnId, dialogText, localizedButtons, callingButton) {
	$(document).ready(function() {
		var title = $(callingButton).text(); // Assign title to calling button's text
		var okButton = localizedButtons[0];
		var cancelButton = localizedButtons[1];
		var d = new Date();
		var UID = Math.ceil(1000 * Math.random(d.getTime()));
		// Construct action to perform when OK and Cancels buttons are clicked
		var dialogOptions = {};
		if(url == null) {
			// Show a simple alert dialog (does not communicate with server)
			dialogOptions[okButton] = function() {
				$(this).dialog("close");
			};
		} else {
			dialogOptions[okButton] = function() {
				// Post to server and construct callback
				$.post(url, '', function(returnString) {
					if (returnString.status) {
						if(returnString.isScript) {
							eval(returnString.script);
						} else {
							updateItem(actType, actOnId, returnString.content);
						}
					} else {
						// Alert that the action failed
						confirm(null, null, null, returnString.content, localizedButtons, callingButton);
					}
				}, 'json');
				$('#'+UID).dialog("close");
			};
			dialogOptions[cancelButton] = function() {
				$(this).dialog("close");
			};
		}

		// Construct dialog
		var $dialog = $('<div id=' + UID + '>'+dialogText+'</div>').dialog({
			title: title,
			autoOpen: false,
			modal: true,
			draggable: false,
			buttons: dialogOptions
		});

		// Tell the calling button to open this modal on click
		$(callingButton).live("click", (function() {
			$dialog.dialog('open');
			return false;
		}));
	});
}

/**
 * Display a simple alert dialog
 * @param dialogText String Text to display in the dialog
 * @param localizedButtons Array of translated 'Cancel/submit' strings
 */
function modalAlert(dialogText, localizedButtons) {
		var localizedText = new Array();
		localizedText = localizedButtons.split(',');
		var okButton = localizedText[0];
		if (localizedText[1]) {
			var title = localizedText[1];
		} else {
			var title = "Alert";
		}
		var d = new Date();
		var UID = Math.ceil(1000 * Math.random(d.getTime()));

		// Construct action to perform when OK button is clicked
		var dialogOptions = {};
		dialogOptions[okButton] = function() {
			$(this).dialog("close");
		};

		// Construct dialog
		var $dialog = $('<div id=' + UID + '>'+dialogText+'</div>').dialog({
			title: title,
			autoOpen: false,
			modal: true,
			draggable: false,
			buttons: dialogOptions
		});

		$dialog.dialog('open');
		return false;
}

/**
 * FIXME: document
 */
function changeModalFormLocale() {
	oldLocale = $("#currentLocale").val();
	newLocale = $("#formLocale").val();

	$("#currentLocale").val(newLocale);
	$("."+oldLocale).hide();
	$("."+newLocale).show("normal");
}

/**
 * Clear all fields of a form.
 * @param form jQuery
 */
function clearFormFields(form) {
	$(':input', form).each(function() {
		if(!$(this).is('.static')) {
			switch(this.type) {
				case 'password':
				case 'select-multiple':
				case 'select-one':
				case 'text':
				case 'textarea':
					$(this).val('');
					break;
				case 'checkbox':
				case 'radio':
					this.checked = false;
			}
		}
	});
}

/**
 * Implements a generic ajax action.
 * 
 * NB: Please make sure you correctly unbind previous ajax action events
 * before you call this method.
 * 
 * @param actType String can be either 'get' or 'post', 'post' expects a form as
 *  a child element of 'actOnId' if no form has been explicitly given.
 * @param callingElement String selector of the element that triggers the ajax call
 * @param url String the url to be called, defaults to the form action in case of
 *  action type 'post'.
 * @param data Array (post action type only) the data to be posted, defaults to
 *  the form data.
 * @param eventName String the name of the event that triggers the action, default 'click'.
 * @param form String the selector of a form element.
 */
function ajaxAction(actType, actOnId, callingElement, url, data, eventName, form) {
	if (actType == 'post') {
		eventHandler = function() {
			// jQuerify the form.
			if (form) {
				$form = $(form);
			} else {
				$form = $(actOnId).find('form');
			}

			// Set default url and data if none has been given.
			if (!url) {
				postUrl = $form.attr("action");
			} else {
				postUrl = url;
			}
			if (!data) {
				postData = $form.serialize();
			} else {
				postData = data;
			}

			// Validate
			validator = $form.validate();

			// Post to server and construct callback
			if ($form.valid()) {
				$actOnId = $(actOnId);
				$actOnId.triggerHandler('actionStart');
				$.post(
					postUrl,
					postData,
					function(jsonData) {
						$actOnId.triggerHandler('actionStop');
						
						// An AJAX action will always return content that
						// replaces the original content independent of whether
						// an error occured or not.
						$actOnId.replaceWith(jsonData.content);
					},
					'json'
				);
				validator = null;
			}
		};
	} else {
		eventHandler = function() {
			$actOnId = $(actOnId);
			$actOnId.triggerHandler('actionStart');
			$.getJSON(
				url,
				function(jsonData) {
					$actOnId.triggerHandler('actionStop');
					if (jsonData.status === true) {
						$actOnId.replaceWith(jsonData.content);
					} else {
						// Alert that the action failed
						alert(jsonData.content);
					}
				}
			);
			return false;
		};
	}

	if (eventName === undefined) eventName = 'click';
	$(callingElement).each(function() {
		// NB: We cannot unbind previous events here as this
		// may delete other legitimate events. Please make sure
		// you correctly unbind previous ajax action events
		// before you call this method.
		$(this).bind(eventName, eventHandler);
	});
}

/**
 * Binds to the "actionStart" event to delete
 * the current contents of the actOnId
 * element and show a throbber instead.
 * @param actOnId the element to be filled with the throbber image.
 */
function actionThrobber(actOnId) {
	$(actOnId).bind('actionStart', function() {
		$(this).html('<div id="actionThrobber" class="throbber"></div>');
		$('#actionThrobber').show();
	});
}

/**
 * Update the DOM of a grid depending on the action type.
 * 
 * NB: This relies on an element with class "empty" being present
 * in the grid. Make sure you use an appropriate grid template for
 * this to work.
 * 
 * @param actType String one of the action type constants. 
 * @param actOnId Selector for the DOM element to be changed.
 * @param content The content that replaces the current DOM element (replace or append types only)
 */
function updateItem(actType, actOnId, content) {
	switch (actType) {
		case 'append':
			$empty = $(actOnId).find('.empty');
			$empty.hide();
			$(actOnId).append(content);
			break;
		case 'replace':
			$(actOnId).replaceWith(content);
			break;
		case 'replaceAll':
			var $p = $(actOnId + ' > .empty').prev();
			$p.html(content);
			break;
		case 'remove':
			if ($(actOnId).siblings().length == 0) {
				deleteElementById(actOnId, true);
			} else {
				deleteElementById(actOnId);
			}
			break;
	}

	// Trigger custom event so that clients can take
	// additional action.
	$(actOnId).triggerHandler('updatedItem', [actType]);
}

/**
 * Deletes the given grid element from the DOM.
 * 
 * NB: This relies on an element with class "empty" being present
 * in the grid. Make sure you use an appropriate grid template for
 * this to work.
 * 
 * @param element String a selector for the element to delete.
 * @param showEmpty Boolean whether to show the "empty" element. 
 */
function deleteElementById(element, showEmpty) {
	var $emptyRow = $(element).parent().siblings('.empty');
	$(element).fadeOut(500, function() {
		$(this).remove();
		if (showEmpty) {
			$emptyRow.fadeIn(500);
		}
	});
}

/**
 * FIXME: document
 * @param url String
 * @param actOnType String
 * @param actOnId String
 * @param tabContainer String
 * @param reopen Boolean
 */
function saveAndUpdate(url, actOnType, actOnId, tabContainer, reopen) {
	$.post(url, null, function(returnString) {
		if (returnString.status == true) {
			updateItem(actOnType, actOnId, returnString.content);
			$(tabContainer).parent().dialog('close');
			if (reopen == true) {
				$(tabContainer).parent().dialog('open');
			}
		} else {
			// Display errors in error list
			$('#formErrors .formErrorList').html(returnString.content);
		}
	}, "json");
}
