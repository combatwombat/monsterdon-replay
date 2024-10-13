class XTimeline extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });

        this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
          width: 100%;
          height: 60px;
          position: relative;
        }
        #touch-area {
          width: 100%;
          height: 100%;
          position: relative;
          cursor: pointer;
        }
        #track-container {
          position: absolute;
          left: 0;
          right: 0;
          top: 50%;
          transform: translateY(-50%);
          height: 3px;
        }
        #track {
          width: 100%;
          height: 100%;
          background-color: #444;
          position: relative;
          border-radius: 3px;
        }
        #filled-track {
          height: 100%;
          background-color: #eee;
          position: absolute;
          left: 0;
          top: 0;
          border-radius: 3px;
        }
        #handle {
          width: 12px;
          height: 12px;
          background-color: #eee;
          border-radius: 6px;
          position: absolute;
          top: 50%;
          transform: translate(-50%, -50%);
          pointer-events: none;
        }
      </style>
      <div id="touch-area" part="touch-area">
        <div id="track-container" part="track-container">
          <div id="track" part="track">
            <div id="filled-track" part="filled-track"></div>
          </div>
        </div>
        <div id="handle" part="handle"></div>
      </div>
    `;

        this.touchArea = this.shadowRoot.getElementById('touch-area');
        this.track = this.shadowRoot.getElementById('track');
        this.filledTrack = this.shadowRoot.getElementById('filled-track');
        this.handle = this.shadowRoot.getElementById('handle');

        this.isActive = false;

        this.onMove = this.onMove.bind(this);
        this.onEnd = this.onEnd.bind(this);

        this.touchArea.addEventListener('mousedown', this.onStart.bind(this));
        this.touchArea.addEventListener('touchstart', this.onStart.bind(this));
    }

    static get observedAttributes() {
        return ['value', 'min', 'max', 'step'];
    }

    connectedCallback() {
        this.updateValue();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue) {
            this.updateValue();
        }
    }

    get value() {
        return parseFloat(this.getAttribute('value')) || 0;
    }

    set value(val) {
        this.setAttribute('value', val);
    }

    get min() {
        return parseFloat(this.getAttribute('min')) || 0;
    }

    get max() {
        return parseFloat(this.getAttribute('max')) || 100;
    }

    get step() {
        return parseFloat(this.getAttribute('step')) || 1;
    }

    updateValue() {
        const percentage = (this.value - this.min) / (this.max - this.min) * 100;
        this.filledTrack.style.width = `${percentage}%`;
        this.handle.style.left = `${percentage}%`;
    }

    onStart(event) {
        event.preventDefault();
        this.isActive = true;
        document.addEventListener('mousemove', this.onMove);
        document.addEventListener('touchmove', this.onMove);
        document.addEventListener('mouseup', this.onEnd);
        document.addEventListener('touchend', this.onEnd);
        this.onMove(event);
    }

    onMove(event) {
        if (!this.isActive) return;

        const rect = this.touchArea.getBoundingClientRect();
        const x = (event.clientX || event.touches[0].clientX) - rect.left;
        const percentage = Math.max(0, Math.min(1, x / rect.width));
        const newValue = this.min + percentage * (this.max - this.min);
        const steppedValue = Math.round(newValue / this.step) * this.step;

        if (steppedValue !== this.value) {
            this.value = steppedValue;
            this.updateValue();
            this.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    onEnd() {
        this.isActive = false;
        document.removeEventListener('mousemove', this.onMove);
        document.removeEventListener('touchmove', this.onMove);
        document.removeEventListener('mouseup', this.onEnd);
        document.removeEventListener('touchend', this.onEnd);
    }
}

customElements.define('x-timeline', XTimeline);