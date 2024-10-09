ready(() => {

    if (find('.page-movie')) {

        // show / hide info box

        const movieInfo = find('.movie .info');
        const openMovieInfo = find('.movie-info');
        const closeMovieInfo = movieInfo.find('.close');

        openMovieInfo.on('click', (e) => {
            e.preventDefault();
            movieInfo.show();
            openMovieInfo.hide();
        });

        closeMovieInfo.on('click', (e) => {
            e.preventDefault();
            movieInfo.hide();
            openMovieInfo.show();
        });


    }

});


async function TootPlayer(slug) {

    const body = find('body');
    const tootsContainer = find('.toots');

    let toots = await request("/api/toots/" + slug, "GET", [], "json");

    if (toots) {

        /*
        example toot json:
        {
id: "113223794851828583",
url: "https://mastodon.murkworks.net/@moira/113223794771069808",
account: {
id: "109296540161788540",
display_name: "Solarbird :flag_cascadia:"
},
content: "<p>WE GOT ALIEN MEGABRAIN SIIIIIIIIIIIIIIIIGN</p><p><a href="https://mastodon.murkworks.net/tags/monsterdon" class="mention hashtag" rel="nofollow noopener noreferrer" target="_blank">#<span>monsterdon</span></a> <a href="https://mastodon.murkworks.net/tags/InvadersFromMars1986" class="mention hashtag" rel="nofollow noopener noreferrer" target="_blank">#<span>InvadersFromMars1986</span></a></p>",
sensitive: false,
created_at: "2024-09-30T01:00:01.000Z",
media_attachments: [ ]
},
         */


        // build html elements for each toot

        toots.forEach( (toot, index) => {

            // create toot element with string literals
            let tootHTML = `
            <div class="toot" data-id="${toot.id}">
                <div class="toot-header">
                    <a href="${toot.account.url}" target="_blank" class="col col-image">
                        <img src="/media/avatars/${toot.account.id}.jpg" alt="${toot.account.display_name}" loading="lazy">
                    </a>
                    <a href="${toot.account.url}" target="_blank" class="col col-name">
                        <div class="display-name">${toot.account.display_name}</div>
                        <div class="acct">${toot.account.acct}</div>
                    </a>
                    <div class="col col-created_at">
                        date
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

            tootsContainer.append(tootElement);

        });

        body.classList.add("toots-loaded");


    }

}