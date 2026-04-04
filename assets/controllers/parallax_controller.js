import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['track', 'phone'];

    connect() {
        this._onScroll = this._handleScroll.bind(this);
        this._onResize = this._handleResize.bind(this);

        window.addEventListener('scroll', this._onScroll, { passive: true });
        window.addEventListener('resize', this._onResize, { passive: true });

        this._ticking = false;
        this._handleResize();
        this._handleScroll();
    }

    disconnect() {
        window.removeEventListener('scroll', this._onScroll);
        window.removeEventListener('resize', this._onResize);
    }

    _handleResize() {
        this._vh = window.innerHeight;
    }

    _handleScroll() {
        if (this._ticking) return;
        this._ticking = true;

        requestAnimationFrame(() => {
            this._ticking = false;
            this._update();
        });
    }

    _update() {
        const track = this.trackTarget;
        const rect = track.getBoundingClientRect();
        const trackScrollable = track.offsetHeight - this._vh;

        // How far through the parallax track we've scrolled (0 → 1)
        const trackProgress = Math.max(0, Math.min(1, -rect.top / trackScrollable));

        const actCount = 4;
        const rawAct = trackProgress * actCount;
        const currentAct = Math.min(Math.floor(rawAct) + 1, actCount);
        const actProgress = rawAct % 1;

        // Set the current act as a data attribute for CSS selectors
        this.element.dataset.act = currentAct;
        this.element.style.setProperty('--act-progress', actProgress.toFixed(3));
        this.element.style.setProperty('--track-progress', trackProgress.toFixed(3));

        // Per-act progress (0→1 within each act's scroll range)
        for (let i = 1; i <= actCount; i++) {
            const p = Math.max(0, Math.min(1, rawAct - (i - 1)));
            this.element.style.setProperty(`--act-${i}`, p.toFixed(3));
        }

        // Action button press effect in act 2
        if (this.hasPhoneTarget) {
            const actionBtn = this.phoneTarget.querySelector('.iphone-action-btn');
            if (actionBtn) {
                const pressDepth = currentAct >= 2 ? 1 : parseFloat(this.element.style.getPropertyValue('--act-1')) || 0;
                actionBtn.style.setProperty('--press', pressDepth.toFixed(3));
            }
        }
    }
}
