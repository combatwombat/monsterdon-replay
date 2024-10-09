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


Element.prototype.on = Element.prototype.addEventListener;

const find = document.querySelector.bind(document);
const findAll = document.querySelectorAll.bind(document);

Element.prototype.find = Element.prototype.querySelector;
Element.prototype.findAll = Element.prototype.querySelectorAll;

Element.prototype.show = function () {
    this.style.display = this.dataset._display || 'block';
}

Element.prototype.hide = function () {
    this.dataset._display = window.getComputedStyle(this, null).display; // remember original display
    this.style.display = 'none';
}


/*
// stuff for alpine state merge

// thx to Salakar https://stackoverflow.com/a/34749873/1191375
function isObject(item) {
    return (item && typeof item === 'object' && !Array.isArray(item));
}

function deepMerge(target, ...sources) {
    if (!sources.length) return target;
    const source = sources.shift();

    if (isObject(target) && isObject(source)) {
        for (const key in source) {
            if (isObject(source[key])) {
                if (!target[key]) Object.assign(target, { [key]: {} });
                deepMerge(target[key], source[key]);
            } else {
                Object.assign(target, { [key]: source[key] });
            }
        }
    }

    return deepMerge(target, ...sources);
}

// change alpine state in a global store
function changeState(changedState, store = "app") {
    if (typeof Alpine === "undefined") {
        document.addEventListener('alpine:init', () => {
            deepMerge(Alpine.store(store).state, changedState);
        });
    } else {
        deepMerge(Alpine.store(store).state, changedState);
    }
}

// send header("HX-Trigger: refresh") to refresh the page
document.body.addEventListener("refresh", () => {
    window.location.href = window.location.href;
});
*/


