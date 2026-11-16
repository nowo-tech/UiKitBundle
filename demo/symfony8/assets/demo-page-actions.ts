/**
 * Demo Stimulus app of /kit/page-actions-dialogs (UiKit 2.52.2, WCAG 2.4.3): the kit's `confirm-dialog` and
 * `remote-form-dialog` peers, and a copy of symfony-beacon's `page-actions` controller — a menu item with
 * `data-page-actions-opens-param="<selector>"` closes the popover menu and forwards its click to that (hidden) dialog
 * trigger, as Beacon's `opens:` page actions do. Built by the bundle root (`pnpm run build:demo-peers`) into
 * public/js/demo-page-actions.js (IIFE, committed). Demo only: a real host registers the peers in its own app.
 */
import { Application, Controller } from '@hotwired/stimulus';

import ConfirmDialogController from '../../../src/Resources/assets/stimulus-peers/confirm_dialog_controller';
import RemoteFormDialogController from '../../../src/Resources/assets/stimulus-peers/remote_form_dialog_controller';

class PageActionsController extends Controller<HTMLElement> {
  open(event: Event & { params: { opens?: string } }): void {
    const selector = event.params.opens ?? '';
    const target = selector === '' ? null : document.querySelector<HTMLElement>(selector);
    if (target === null) {
      return;
    }
    event.preventDefault();
    this.element.querySelector<HTMLElement>('[popover]')?.hidePopover?.();
    target.click();
  }
}

/** The remote form's `saved` event: stay on this page (no redirect) and say so, so focus return can be checked. */
class StayController extends Controller<HTMLElement> {
  static override targets = ['status'];
  declare readonly statusTarget: HTMLElement;

  saved(event: Event): void {
    event.preventDefault();
    this.statusTarget.textContent = 'Saved.';
  }
}

const app = Application.start();
app.register('confirm-dialog', ConfirmDialogController);
app.register('remote-form-dialog', RemoteFormDialogController);
app.register('page-actions', PageActionsController);
app.register('demo-stay', StayController);
