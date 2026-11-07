/**
 * Demo Stimulus app (UiKit 2.23 demos; 2.30: the fold toggle of the page-contract orders filters): registers the kit
 * peers those pages use. Built by the bundle root (`pnpm run build:demo-peers`) into public/js/demo-peers.js (IIFE, committed). A real host registers the peers in
 * its own Stimulus app (docs/STIMULUS.md).
 */
import { Application } from '@hotwired/stimulus';

import ConfirmSubmitController from '../../../src/Resources/assets/stimulus-peers/confirm_submit_controller';
import FiltersCollapseController from '../../../src/Resources/assets/stimulus-peers/filters_collapse_controller';
import ListPaginationController from '../../../src/Resources/assets/stimulus-peers/list_pagination_controller';
import MultiSelectController from '../../../src/Resources/assets/stimulus-peers/multi_select_controller';
import VisibleWhenController from '../../../src/Resources/assets/stimulus-peers/visible_when_controller';

const app = Application.start();
app.register('confirm-submit', ConfirmSubmitController);
app.register('filters-collapse', FiltersCollapseController);
app.register('list-pagination', ListPaginationController);
app.register('multi-select', MultiSelectController);
app.register('visible-when', VisibleWhenController);
