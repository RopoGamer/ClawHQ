import { startStimulusApp } from '@symfony/stimulus-bundle';
import PollController from './controllers/poll_controller.js';
import TaskModalController from './controllers/task_modal_controller.js';

const app = startStimulusApp();
app.register('poll', PollController);
app.register('task-modal', TaskModalController);
