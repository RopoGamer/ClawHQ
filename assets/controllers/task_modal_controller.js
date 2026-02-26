import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'body'];

    connect() {
        const modalCtor = window.bootstrap?.Modal;
        if (this.hasModalTarget && modalCtor) {
            this.modal = new modalCtor(this.modalTarget);
        }
    }

    async open(event) {
        const url = event.currentTarget.dataset.taskModalUrl;
        if (!url || !this.modal || !this.hasBodyTarget) {
            return;
        }

        this.bodyTarget.innerHTML = '<p class="text-body-secondary mb-0">Loading task details...</p>';
        this.modal.show();

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                this.bodyTarget.innerHTML = '<p class="text-danger mb-0">Unable to load task details.</p>';
                return;
            }

            this.bodyTarget.innerHTML = await response.text();
        } catch {
            this.bodyTarget.innerHTML = '<p class="text-danger mb-0">Unable to load task details.</p>';
        }
    }
}
