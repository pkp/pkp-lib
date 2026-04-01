(function() {
  // Patch the validator: place errors on group fieldsets and add a single required rule per group
  function applyPatches(validator, $form) {
    if (!validator || validator.isPatched) return;
    validator.isPatched = true;

    // Error placement: for radios/checkboxes in reviewFormResponses, append to their fieldset
    const originalErrorPlacement = validator.settings.errorPlacement;
    validator.settings.errorPlacement = function(error, element) {
      const name = element.attr('name') || '';
      if (element.is(':checkbox, :radio') && name.indexOf('reviewFormResponses[') === 0) {
        const fieldset = element.closest('fieldset');
        const fsId = fieldset.attr('id') || '';
        if (fieldset.length && fsId.startsWith('reviewFormResponses-')) {
          error.appendTo(fieldset);
          return;
        }
      }
      if (typeof originalErrorPlacement === 'function') {
        originalErrorPlacement(error, element);
      } else {
        error.insertAfter(element);
      }
    };

    // Add required rule once per required fieldset in this form for radios/checkboxes
    $form.find('fieldset[aria-required="true"]').each(function() {
      const $fs = $(this);
      if ($fs.data('rulesAdded')) return;
      $fs.data('rulesAdded', true);

      const $inputs = $fs
        .find('input[type="radio"], input[type="checkbox"]')
        .filter(':not(:disabled)');
      if ($inputs.length) {
        $inputs.first().rules('add', { required: true });
      }
    });

    // When "Save for Later" is clicked, skip validation entirely
    $form.find('[name="saveFormButton"]').on('click', function() {
      validator.cancelSubmit = true;
    });

    // This part is for the bottom warning box
    const $box = $form.find('#reviewStep3MessageBox');
    // Show warning box when validator detects invalid submit
    $form.on('invalid-form.validate', function() {
        $box.show();
    });
    // Hide warning box when form becomes valid again
    $form.on('change keyup', ':input', function() {
        if ($form.valid && $form.valid()) {
            $box.hide();
        }
    });
    
  }

  // Single jQuery Validation hook: runs exactly when the form’s validator is created
  (function installValidateHook() {
    const $ = window.jQuery;
    if (!$ || !$.fn || !$.fn.validate || $.fn.__validationHookPatched) return;

    $.fn.__validationHookPatched = true;
    const origValidate = $.fn.validate;
    $.fn.validate = function(options) {
      const res = origValidate.apply(this, arguments);
      const $form = this;
      if ($form.attr('id') === 'reviewStep3Form') {
        applyPatches($form.data('validator'), $form);
      }
      return res;
    };
  })();

})();