// onready
function ready(fn) {
    if (document.readyState != 'loading') {
        fn();
    } else {
        document.addEventListener('DOMContentLoaded', fn);
    }
}

/**
 * Simple HTTP request wrapper
 * @param method
 * @param url
 * @param data array of key-value pairs
 * @returns {Promise<Response>}
 */
async function httpRequest(url, method = "GET", data = []) {
    if (method === "GET") {
        url += "?" + new URLSearchParams(data).toString();
    }
    let res = await fetch(url, {
         method: method,
         headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
         },
         body: method === "POST" ? new URLSearchParams(data) : undefined
    });
    return await res.text()
}
