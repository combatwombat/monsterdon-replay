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

    updateDisplay();

    // if playing, call function repeatedly with requestAnimationFrame
    function startPlaying() {
        playing = true;
        els.player.classList.add("playing");

        startTime = performance.now() / 1000;
        startCurrentTime = currentTime;
        oldCurrentTime = currentTime - 1;
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
    document.on('click', (e) => {
        if (!els.player.contains(e.target)) {
            els.player.classList.remove("settings-open");
        }
    });

    on("click", ".settings .col-label", (e) => {
       // parent.{.col-checkbox}.input.click
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

    els.tootsStartButton.on('click', (e) => {
        e.preventDefault();
        els.body.classList.add("playing-started");
        startPlaying();
    });




}