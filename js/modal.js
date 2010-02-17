/**
 * modal.js
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Implementation of jQuery modals for OMP.
 *
 * $Id:
 */

/**
 * modal
 * @param $url URL to load into the modal
 * @param $actType Type to define if callback should do (nothing|append|replace|remove)
 * @param $actOnId The ID on which to perform the action on callback
 * @param $apendTo Selector to append data to
 * @param $localizedButtons Array of translated 'Cancel/submit' strings
 * @param $callingButton Selector of the button that opens the modal
 */
function modal(url, actType, actOnId, localizedButtons, callingButton) {
	$(document).ready(function() {
		var validator = null;
		var title = $(callingButton).text(); // Assign title to calling button's text
		var okButton = localizedButtons[0];
		var cancelButton = localizedButtons[1];
		var UID = new Date().getTime();
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
				$form =  $(formContainer).find('form');
				validator = $form.validate();
				// Post to server and construct callback
				if ($form.valid()) {
					$.post(
						$form.attr("action"),
						$form.serialize(),
						function(returnString) {
							if (returnString.status == true) {
								updateItem(actType, actOnId, returnString.content);
								$('#' + UID).dialog("close");
							} else {
								// Display errors in error list
								$('#formErrors .formErrorList').html(returnString.content);
							}
						},
						"json"
					);
					validator = null;
				}
			};
			dialogOptions[cancelButton] = function() {
				$(this).dialog("close");
			};
		}

		// Construct dialog
		var $dialog = $('<div id=' + UID + '></div>').dialog({
			title: title,
			autoOpen: false,
			width: 600,
			modal: true,
			draggable: false,
			buttons: dialogOptions,
			open: function(event, ui) {
				$(this).load(url, null, function() {
					$('#loading').throbber("disable");
				});
				$(this).html("<div id='loading' class='throbber'></div>");
				$('#loading').throbber({
					bgcolor: "#CED7E1",
					speed: 1
				});
				$('#loading').throbber("enable");
			},
			close: function() {
				// Reset form validation errors and inputs on close
				if (validator != null) {
					validator.resetForm();
				}
				clearFormFields($(formContainer).find('form'));
			}
		});

		// Tell the calling button to open this modal on click
		$(callingButton).live("click", (function() {
			$dialog.dialog('open');
			return false;
		}));

	});
}

/**
 * confirm
 * @param $url URL to load into the modal
 * @param $actType Type to define if callback should do (nothing|append|replace|remove)
 * @param $actOnId The ID on which to perform the action on callback
 * @param $dialogText Text to display in the dialog
 * @param $localizedButtons Array of translated 'Cancel/submit' strings
 * @param $callingButton Selector of the button that opens the modal
 */
function modalConfirm(url, actType, actOnId, dialogText, localizedButtons, callingButton) {
	$(document).ready(function() {
		var title = $(callingButton).text(); // Assign title to calling button's text
		var okButton = localizedButtons[0];
		var cancelButton = localizedButtons[1];
		var UID = new Date().getTime();

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
						updateItem(actType, actOnId, returnString.content);
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
		$(callingButton).click(function() {
			$dialog.dialog('open');
			return false;
		});
	});
}
/**
 * alert - Display a simple alert dialog
 * @param $dialogText Text to display in the dialog
 * @param $localizedButtons Array of translated 'Cancel/submit' strings
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
		var UID = new Date().getTime();

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

function changeModalFormLocale() {
	oldLocale = $("#currentLocale").val();
	newLocale = $("#formLocale").val();

	$("#currentLocale").val(newLocale);
	$("."+oldLocale).hide();
	$("."+newLocale).show("normal");
}

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
 * buttonPost
 * Call a simple POST request when clicking on a button
 * @param $url URL to POST to
 * @param $callingButton Selector of the button that opens the modal
 */
function buttonPost(url, callingButton) {
	$(document).ready(function() {
		$(callingButton).click(function(){
			$.post(url, function(returnString) {
				if (returnString.status == true) {
					alert('success');
				} else {
					alert('failure');
				}
			}, 'json');
		});
	});
}

function updateItem(actType, actOnId, content) {
	switch (actType) {
		case 'append':
			$('#' + actOnId + ' > .empty').hide();
			$('#' + actOnId).append(content);
			break;
		case 'replace':
			var $p = $('#' + actOnId).prev();
			if($p.size() == 0) {
				$a = $('#' + actOnId).next();
				$('#' + actOnId).remove();
				$a.before(content);
			} else {
				$('#' + actOnId).remove();
				$p.after(content);
			}
			break;
		case 'remove':
			if ($('#' + actOnId).siblings().length == 1) {
				deleteElementById(actOnId, true);
			} else {
				deleteElementById(actOnId);
			}
			break;
	}
}

function deleteElementById(elementId, showEmpty) {
	var $emptyRow = $('#' + elementId).siblings('.empty');
	$("#"+elementId).fadeOut(500, function() {
		$(this).remove();
		if (showEmpty) {
			$emptyRow.fadeIn(500);
		}
	});
}
