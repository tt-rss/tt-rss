(function () {
  // make login and other ttrss_utility pages mobile-friendly (needs local-overrides.css)
  var meta = document.createElement("meta");
  meta.name = "viewport";
  meta.content = "width=device-width, initial-scale=1.0";
  document.querySelector("head").appendChild(meta);

  // polyfill for Safari, from https://github.com/pladaria/requestidlecallback-polyfill/blob/master/index.js
  window.requestIdleCallback =
    window.requestIdleCallback ||
    function (cb) {
      var start = Date.now();
      return setTimeout(function () {
        cb({
          didTimeout: false,
          timeRemaining: function () {
            return Math.max(0, 50 - (Date.now() - start));
          },
        });
      }, 1);
    };

  window.cancelIdleCallback =
    window.cancelIdleCallback ||
    function (id) {
      clearTimeout(id);
    };
})();
