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
 * @returns {Promise<string>}
 */
async function request(url, method = "GET", data = [], responseFormat = "text") {
    if (method === "GET" && data.length > 0) {
        url += "?" + new URLSearchParams(data).toString();
    }
    let res = await fetch(url, {
         method: method,
         headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
         },
         body: method !== "GET" ? new URLSearchParams(data) : undefined
    });
    return responseFormat === "json" ? await res.json() : await res.text();
}


Element.prototype.on = Element.prototype.addEventListener;

const find = document.querySelector.bind(document);
const findAll = document.querySelectorAll.bind(document);