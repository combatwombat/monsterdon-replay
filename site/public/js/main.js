function ready(fn) {
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

function delegate(selector, eventType, handler) {
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
        find('.open-movie-info').on('click', (e) => {
            e.preventDefault();
            body.classList.add("movie-info-open");
        });

        find('.movie-info .close').on('click', (e) => {
            e.preventDefault();
            body.classList.remove("movie-info-open");
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
        inputCurrentTime: find('.input-current-time')
    }

    const overallCurrentTime = els.inputCurrentTime.getAttribute('max');

    let currentTime = 0.0;
    let playing = false;
    let showTimeLeft = true;

    let startTime = 0; // when did we press play?
    let startCurrentTime = 0; // what was the time when we pressed play?
    let oldCurrentTime = 0; // what was the last time we updated the display?

    updateDisplay();

    // if playing, call function repeatedly with requestAnimationFrame
    function startPlaying() {
        playing = true;
        els.player.classList.add("playing");

        startTime = performance.now() / 1000;
        startCurrentTime = currentTime;
        oldCurrentTime = currentTime;
        advanceTime();
    }

    function stopPlaying() {
        playing = false;
        els.player.classList.remove("playing");
    }


    function advanceTime(timestamp) {

        let elapsedTime = (performance.now() / 1000) - startTime;

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
        startTime = performance.now() / 1000;
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


    // build html elements for each toot

    toots.forEach( (toot, index) => {

        // create toot element with string literals
        let tootHTML = `
        <div class="toot" data-id="${toot.id}" style="display: none;">
            <div class="toot-header">
                <a href="${toot.account.url}" target="_blank" class="col col-image">
                    <img src="/media/avatars/${toot.account.id}.jpg" alt="${toot.account.display_name}" loading="lazy">
                </a>
                <a href="${toot.account.url}" target="_blank" class="col col-name">
                    <div class="display-name">${toot.account.display_name}</div>
                    <div class="acct">${toot.account.acct}</div>
                </a>
                <div class="col col-created_at">
                    ${formatTime(toot.time_delta)}
                </div>
            </div>
            <div class="toot-body">
                ${toot.content}
            </div>
        </div>
        `;

        // create dom element from tootHTML
        let tootElement = document.createElement('div');
        tootElement.innerHTML = tootHTML.trim();
        tootElement = tootElement.firstChild;

        toots[index].el = tootElement;

        els.tootsContainer.append(tootElement);

    });

    els.body.classList.add("toots-loaded");

    // scrub on timeline
    els.inputCurrentTime.on('input', (e) => {
        onTimeScrub(parseInt(e.target.value));
    });

    // play / pause
    els.playPauseButton.on('click', (e) => {
        e.preventDefault();
        if (playing) {
            stopPlaying();
        } else {
            startPlaying();
        }
    });

    // toggle between showing the time left and overall time
    els.overallTime.on('click', (e) => {
        showTimeLeft = !showTimeLeft;
        updateDisplay();
    });


}