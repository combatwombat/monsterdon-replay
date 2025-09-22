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

customElements.define('x-timeline', XTimeline);function ready(fn) {
    if (document.readyState != 'loading') {
        fn();
    } else {
        document.addEventListener('DOMContentLoaded', fn);
    }
}

/**
 * Simple HTTP fetch request wrapper for form data
 * @param url
 * @param method
 * @param data array of key-value pairs
 * @param responseFormat "text" or "json"
 * @param headers object of additional headers
 * @returns {Promise<string>}
 */
async function request(url, method = "GET", data = [], responseFormat = "text", headers = {}) {
    if (method === "GET" && data.length > 0) {
        url += "?" + new URLSearchParams(data).toString();
    }
    let res = await fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            ...headers
        },
        body: method !== "GET" ? new URLSearchParams(data) : undefined
    });
    return responseFormat === "json" ? await res.json() : await res.text();
}


globalThis.find = document.querySelector.bind(document);
globalThis.findAll = document.querySelectorAll.bind(document);

Element.prototype.find = Element.prototype.querySelector;
Element.prototype.findAll = Element.prototype.querySelectorAll;

// shortcut for addEventListener: find('.movie-info').on('click', (e) => { ....
Element.prototype.on = Element.prototype.addEventListener;
document.on = document.addEventListener;

function delegate(eventType, selector, handler) {
    document.addEventListener(eventType, function(event) {
        const targets = document.querySelectorAll(selector);
        const target = event.target;

        for (let i = 0; i < targets.length; i++) {
            let el = target;
            while (el && el !== this) {
                if (el === targets[i]) {
                    handler.call(el, event);
                    return;
                }
                el = el.parentNode;
            }
        }
    }, true);
}

// event handling for possibly live changing elements: on('.list-item', 'click', (e) => { ...
globalThis.on = delegate;



Element.prototype.show = function () {
    this.style.display = this.dataset._display || 'block';
}

Element.prototype.hide = function () {
    this.dataset._display = window.getComputedStyle(this, null).display; // remember original display
    this.style.display = 'none';
}

// create DOM element(s) from HTML string
globalThis.create = function (string) {
    const template = document.createElement('template');
    template.innerHTML = string.trim();
    return template.content.firstChild;
}


/**
 * Format seconds to MM:SS or H:MM:SS if it's over an hour
 * @param seconds
 * @returns {string}
 */
function formatTime(seconds) {
    let hours = Math.floor(seconds / 3600);
    let minutes = Math.floor(seconds % 3600 / 60);
    let secs = Math.floor(seconds % 60);

    let timeString = "";
    if (hours > 0) {
        timeString += hours + ":";
    }
    timeString += (minutes < 10 ? "0" : "") + minutes + ":";
    timeString += (secs < 10 ? "0" : "") + secs;
    return timeString;
}


ready(() => {

    if (find('.page-movie')) {

        // show / hide info box
        const body = find('body');
        const header = find('header');
        find('.open-movie-info').on('click', (e) => {
            e.preventDefault();
            body.classList.toggle("movie-info-closed");
        });

        find('.movie-info .close').on('click', (e) => {
            e.preventDefault();
            body.classList.add("movie-info-closed");
        });

    }

});


async function TootPlayer(slug) {

    let toots = await request("/api/toots/" + slug, "GET", [], "json");

    if (!toots) {
        return;
    }

    const els = {
        body: find('body'),
        player: find('.player'),
        tootsContainer: find('.toots'),
        currentTime: find('.current-time'),
        overallTime: find('.overall-time'),
        playPauseButton: find('.play-pause-button'),
        inputCurrentTime: find('.input-current-time'),
        openSettings: find('.open-settings'),
        inputSettingCompact: find('#setting-compact'),
        inputSettingHideHashtags: find('#setting-hide-hashtags'),
        tootsStartButton: find('.toots-start-button'),
    }

    const overallCurrentTime = els.inputCurrentTime.getAttribute('max');

    let currentTime = 0.0;
    let playing = false;
    let showTimeLeft = true;

    let startTime = 0; // when did we press play?
    let startCurrentTime = 0; // what was the time when we pressed play?
    let oldCurrentTime = -1; // what was the last time we updated the display?

    let prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    updateDisplay();

    function startPlaying() {
        playing = true;
        els.player.classList.add("playing");

        startTime = Date.now() / 1000;
        startCurrentTime = currentTime;
        oldCurrentTime = currentTime - 1;
        advanceTime();
    }

    function stopPlaying() {
        playing = false;
        els.player.classList.remove("playing");
    }


    function advanceTime(timestamp) {

        let elapsedTime = (Date.now() / 1000) - startTime;

        currentTime = startCurrentTime + elapsedTime;

        if (currentTime > overallCurrentTime) {
            currentTime = overallCurrentTime;
            stopPlaying();
        }
        if (Math.floor(oldCurrentTime) !== Math.floor(currentTime)) {
            updateDisplay();
            showTootsBeforeTime(currentTime);
        }

        oldCurrentTime = currentTime;

        if (playing) {
            requestAnimationFrame(advanceTime);
        }

    }


    // display all toots that have a time_delta before the given time
    function showTootsBeforeTime(seconds) {
        toots.forEach( (toot) => {
            if (toot.time_delta <= seconds) {
                toot.el.style.display = 'block';
            } else {
                toot.el.style.display = 'none';
            }
        });
    }

    function onTimeScrub(seconds) {
        startTime = Date.now() / 1000;
        currentTime = seconds;
        startCurrentTime = currentTime;
        updateDisplay();
        showTootsBeforeTime(seconds);
    }

    function updateDisplay() {
        els.currentTime.innerText = formatTime(currentTime);
        els.inputCurrentTime.value = currentTime;

        if (showTimeLeft) {
            const timeLeft = overallCurrentTime - currentTime;
            els.overallTime.innerText = "-" + formatTime(timeLeft);

        } else {
            els.overallTime.innerText = formatTime(overallCurrentTime);
        }

    }


    // extra wrapper to append toots to, instead of directly into the dom. slightly faster.
    let tootsWrapper = document.createElement('div');

    // build html elements for each toot
    toots.forEach( (toot, index) => {

        // create toot element with string literals
        let tootHTML = `<div class="toot" style="display: none;" data-id="${toot.id}">
            <a href="${toot.url}" target="_blank" class="toot-header">
                <div class="col col-image">
                    <img src="/media/avatars/${toot.account.id}.jpg" alt="${toot.account.display_name}" loading="lazy">
                </div>
                <div class="col col-name">
                    <div class="display-name">${toot.account.display_name}</div>
                    <div class="acct">${toot.account.acct}</div>
                </div>
                <div class="col col-created_at">
                    ${formatTime(toot.time_delta)}
                </div>
            </a>
            <div class="toot-body">
                ${toot.content}
            </div>`

        if (toot.media_attachments.length > 0) {

            tootHTML += `<div class="toot-media-attachments">`;

            toot.media_attachments.forEach( (media) => {



                if (media.type === "image") {
                    tootHTML += `<div class="media media-image"><a href="/media/originals/${media.id}.${media.extension}" target="_blank"><img src="/media/previews/${media.id}.jpg" alt="${media.description}" loading="lazy"></a></div>`;

                } else if (media.type === "video") {
                    tootHTML += `<div class="media media-video"><video controls>
                        <source src="/media/originals/${media.id}.${media.extension}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video></div>`;

                } else if (media.type === "gifv") {
                    tootHTML += `<div class="media media-gifv">
                        <video autoplay loop muted playsinline>
                            <source src="/media/originals/${media.id}.${media.extension}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>`;

                } else {
                    tootHTML += `<div class="media media-misc">
                        <div style="height: 400px; background: red;">${media.type}</div>
                    </div>`;
                }
            });
            tootHTML += `</div>`;
        }

        tootHTML += `</div>`;


        let tootElement = document.createElement('div');
        tootElement.innerHTML = tootHTML.trim();
        tootElement = tootElement.firstChild;

        toots[index].el = tootElement;

        tootsWrapper.append(tootElement);

    });

    els.tootsContainer.append(tootsWrapper);

    els.body.classList.add("toots-loaded");


    // scrub on timeline
    els.inputCurrentTime.on('input', (e) => {
        onTimeScrub(parseInt(e.target.value));
        els.body.classList.add("playing-started");
    });

    // play / pause
    els.playPauseButton.on('click', (e) => {
        e.preventDefault();
        if (playing) {
            stopPlaying();
        } else {
            startPlaying();
            els.body.classList.add("playing-started");
        }
    });

    // toggle between showing the time left and overall time
    els.overallTime.on('click', (e) => {
        showTimeLeft = !showTimeLeft;
        updateDisplay();
    });

    // open settings
    els.openSettings.on('click', (e) => {
        e.preventDefault();
        els.player.classList.toggle("settings-open");
    });

    // click anywhere outside player: close settings
    // don't bubble up click if player is open
    document.on('click', (e) => {
        if (!els.player.contains(e.target)) {
            if (els.player.classList.contains("settings-open")) {
                els.player.classList.remove("settings-open");
                e.preventDefault();
            }
        }
    });

    on("click", ".settings .col-label", (e) => {
         e.target.parentElement.find('.col-checkbox input').click();
    });

    // settings input
    els.inputSettingCompact.on("change", (e) => {
        if (e.target.checked) {
            els.body.classList.add("style-compact");
        } else {
            els.body.classList.remove("style-compact");
        }
    });

    els.inputSettingHideHashtags.on("change", (e) => {
        if (e.target.checked) {
            els.body.classList.add("style-hide-hashtags");
        } else {
            els.body.classList.remove("style-hide-hashtags");
        }
    });

    // start button
    els.tootsStartButton.on('click', (e) => {
        e.preventDefault();
        els.body.classList.add("playing-started");
        startPlaying();
    });

}