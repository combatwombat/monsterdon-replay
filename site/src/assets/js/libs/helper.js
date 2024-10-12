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


