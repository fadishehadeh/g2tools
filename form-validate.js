/**
 * G2 Forms — shared client-side validation.
 * Call G2.validate(form) before submit; returns true if all required fields pass.
 * Attach automatically via data-validate on the form element, or call manually.
 */
(function (G2) {

  function getLabel(el) {
    // Try <label> for= , then closest .field > .field-label
    const id = el.id;
    if (id) {
      const lbl = document.querySelector('label[for="' + id + '"]');
      if (lbl) return lbl.textContent.replace('*', '').trim();
    }
    const field = el.closest('.field');
    if (field) {
      const lbl = field.querySelector('.field-label');
      if (lbl) return lbl.textContent.replace('*', '').trim();
    }
    return el.name || 'This field';
  }

  function setError(el, msg) {
    const field = el.closest('.field') || el.parentElement;
    field.classList.add('field-error');
    let errEl = field.querySelector('.field-error-msg');
    if (!errEl) {
      errEl = document.createElement('div');
      errEl.className = 'field-error-msg';
      // Insert after the input/upload-label
      const anchor = field.querySelector('.upload-label') || el;
      anchor.insertAdjacentElement('afterend', errEl);
    }
    errEl.textContent = '⚠ ' + msg;
  }

  function clearError(el) {
    const field = el.closest('.field') || el.parentElement;
    field.classList.remove('field-error');
    const errEl = field.querySelector('.field-error-msg');
    if (errEl) errEl.textContent = '';
  }

  G2.validate = function (form) {
    let firstError = null;
    let valid = true;

    // ── Text / number / date / email / tel / password inputs ──
    form.querySelectorAll('input[required]:not([type=file]):not([type=checkbox]):not([type=radio])').forEach(function (el) {
      clearError(el);
      if (!el.value.trim()) {
        setError(el, getLabel(el) + ' is required.');
        if (!firstError) firstError = el;
        valid = false;
      }
    });

    // ── Selects ──
    form.querySelectorAll('select[required]').forEach(function (el) {
      clearError(el);
      if (!el.value) {
        setError(el, getLabel(el) + ' is required.');
        if (!firstError) firstError = el;
        valid = false;
      }
    });

    // ── Textareas ──
    form.querySelectorAll('textarea[required]').forEach(function (el) {
      clearError(el);
      if (!el.value.trim()) {
        setError(el, getLabel(el) + ' is required.');
        if (!firstError) firstError = el;
        valid = false;
      }
    });

    // ── File inputs (hidden behind styled labels, marked data-required) ──
    form.querySelectorAll('input[type=file][data-required]').forEach(function (el) {
      const field = el.closest('.field') || el.parentElement;
      const uploadLabel = field.querySelector('.upload-label');
      clearError(uploadLabel || el);
      if (!el.files || !el.files.length) {
        setError(uploadLabel || el, getLabel(el) + ' is required.');
        if (!firstError) firstError = uploadLabel || el;
        valid = false;
      }
    });

    // ── Checkboxes marked required ──
    form.querySelectorAll('input[type=checkbox][required]').forEach(function (el) {
      clearError(el);
      if (!el.checked) {
        setError(el, getLabel(el) + ' must be checked.');
        if (!firstError) firstError = el;
        valid = false;
      }
    });

    if (firstError) {
      firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return valid;
  };

  // Auto-wire any form with data-validate attribute
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!G2.validate(form)) e.preventDefault();
      });

      // Clear error on input
      form.addEventListener('input', function (e) {
        if (e.target.closest('.field')) clearError(e.target);
      });
      form.addEventListener('change', function (e) {
        if (e.target.closest('.field')) clearError(e.target);
      });
    });
  });

}(window.G2 = window.G2 || {}));
