/* global UtilityApp */

/* TODO: this should probably be something like night_mode.js since it does nothing specific to utility scripts */

window.addEventListener("load", function() {
    const UtilityJS = {
        apply_night_mode: function (is_night, link) {
            /* global __default_dark_theme, __default_light_theme */
            const light_theme = typeof __default_light_theme !== 'undefined' ? __default_light_theme : 'themes/light.css';
            const dark_theme = typeof __default_dark_theme !== 'undefined' ? __default_dark_theme : 'themes/night.css';

            if (link) {
                const css_override = is_night ? dark_theme : light_theme;

                link.setAttribute('href', css_override);
            }
        },
        setup_night_mode: function() {
            const mql = window.matchMedia('(prefers-color-scheme: dark)');

            const link = document.createElement("link");

            link.rel = "stylesheet";
            link.id = "theme_auto_css";

            link.onload = function() {
                document.body.classList.remove('css_loading');

                if (typeof UtilityApp !== 'undefined')
                    UtilityApp.init();
            };

            try {
                mql.addEventListener("change", () => {
                    UtilityJS.apply_night_mode(mql.matches, link);
                });
            } catch {
                console.warn("exception while trying to set MQL event listener");
            }

            document.head.prepend(link);

            UtilityJS.apply_night_mode(mql.matches, link);
        }
    };

    UtilityJS.setup_night_mode();
});
