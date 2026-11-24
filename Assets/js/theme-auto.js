(function () {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const classes = document.documentElement.classList;
    function apply(scheme) {
        classes.toggle('theme-dark', scheme.matches);
        classes.toggle('theme-light', !scheme.matches);
    }
    apply(media);
    media.addEventListener('change', apply);
})();
