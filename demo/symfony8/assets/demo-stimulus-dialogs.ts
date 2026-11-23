/**
 * Demo Stimulus app of /kit/stimulus-dialogs/{stimulus,both} (UiKit 2.70): the kit peers a Beacon product page
 * registers instead of loading js/nowo-ui-dialog.js / js/nowo-ui-confirm.js — `remote-form-dialog` (the remote form
 * shell in delegated mode), `confirm-shared` (the shared confirm dialog of the row kebabs) and `confirm-submit` (title
 * and button labels). Built by the bundle root (`pnpm run build:demo-peers`) into public/js/demo-stimulus-dialogs.js
 * (IIFE, committed). Demo only: a real host registers the peers in its own app.
 */
import { Application } from '@hotwired/stimulus';

import ConfirmSharedController from '../../../src/Resources/assets/stimulus-peers/confirm_shared_controller';
import ConfirmSubmitController from '../../../src/Resources/assets/stimulus-peers/confirm_submit_controller';
import RemoteFormDialogController from '../../../src/Resources/assets/stimulus-peers/remote_form_dialog_controller';

const app = Application.start();
app.register('confirm-shared', ConfirmSharedController);
app.register('confirm-submit', ConfirmSubmitController);
app.register('remote-form-dialog', RemoteFormDialogController);
