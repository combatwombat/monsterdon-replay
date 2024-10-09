ready(() => {

    // are we on .page-movie?
    if (find('.page-movie')) {

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