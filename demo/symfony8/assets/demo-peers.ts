/**
 * Demo Stimulus app (UiKit 2.23 demos only): registers the kit peers the 2.23 pages use. Built by the bundle root
 * (`pnpm run build:demo-peers`) into public/js/demo-peers.js (IIFE, committed). A real host registers the peers in
 * its own Stimulus app (docs/STIMULUS.md).
 */
import { Application } from '@hotwired/stimulus';

import ConfirmSubmitController from '../../../src/Resources/assets/stimulus-peers/confirm_submit_controller';
import ListPaginationController from '../../../src/Resources/assets/stimulus-peers/list_pagination_controller';
import VisibleWhenController from '../../../src/Resources/assets/stimulus-peers/visible_when_controller';

const app = Application.start();
app.register('confirm-submit', ConfirmSubmitController);
app.register('list-pagination', ListPaginationController);
app.register('visible-when', VisibleWhenController);
