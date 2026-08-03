/**
 * Minimal modal open/close for custom / none / tailwind stacks (REQ-UI-001).
 * Bootstrap stacks use data-bs-* / data-toggle and must not rely on this script.
 */
(function () {
  'use strict';

  function openModal(id) {
    var el = typeof id === 'string' ? document.getElementById(id) : id;
    if (!el) {
      return;
    }
    el.classList.add('nowo-ui-modal-open');
    document.body.classList.add('nowo-modal-open');
  }

  function closeModal(el) {
    if (!el) {
      return;
    }
    el.classList.remove('nowo-ui-modal-open');
    if (!document.querySelector('.nowo-ui-modal.nowo-ui-modal-open')) {
      document.body.classList.remove('nowo-modal-open');
    }
  }

  window.nowoOpenModal = openModal;
  window.nowoCloseModal = closeModal;

  document.addEventListener('click', function (event) {
    var openBtn = event.target.closest('[data-nowo-modal-open]');
    if (openBtn) {
      var targetId = openBtn.getAttribute('data-nowo-modal-target');
      if (targetId) {
        event.preventDefault();
        openModal(targetId);
      }
      return;
    }

    var closeBtn = event.target.closest('[data-nowo-modal-close]');
    if (closeBtn) {
      var modal = closeBtn.closest('.nowo-ui-modal');
      if (modal) {
        event.preventDefault();
        closeModal(modal);
      }
      return;
    }

    if (event.target.classList && event.target.classList.contains('nowo-ui-modal') && event.target.classList.contains('nowo-ui-modal-open')) {
      closeModal(event.target);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }
    var open = document.querySelector('.nowo-ui-modal.nowo-ui-modal-open');
    if (open) {
      closeModal(open);
    }
  });
})();
