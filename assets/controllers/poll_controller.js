import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];
    static values = {
        url: String,
        interval: { type: Number, default: 5000 },
    };

    connect() {
        this.refresh();
        this.timer = window.setInterval(() => this.refresh(), this.intervalValue);
    }

    disconnect() {
        if (this.timer) {
            window.clearInterval(this.timer);
        }
    }

    async refresh() {
        if (!this.hasContainerTarget || !this.hasUrlValue) {
            return;
        }

        try {
            const response = await fetch(this.urlValue, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            this.containerTarget.innerHTML = await response.text();
        } catch {
            // Keep the current UI state when polling fails.
        }
    }
}
