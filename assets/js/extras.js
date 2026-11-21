/* extras.js: cookie notice. Remembers the choice in this browser (localStorage). */
(function () {
  "use strict";
  var KEY = "cookie-notice-ok";
  var box = document.getElementById("cookie-notice");
  if (!box) return;

  var seen = false;
  try {
    seen = localStorage.getItem(KEY) === "1";
  } catch (e) {
    /* storage blocked: show the notice each visit */
  }
  if (seen) return;

  // Wait until the pre-loader is gone so the two never overlap
  function show() {
    box.hidden = false;
  }
  var root = document.documentElement;
  if (root.classList.contains("is-loading")) {
    var tries = 0;
    var timer = setInterval(function () {
      tries += 1;
      if (!root.classList.contains("is-loading") || tries > 60) {
        clearInterval(timer);
        show();
      }
    }, 250);
  } else {
    show();
  }

  var button = box.querySelector("[data-cookie-accept]");
  if (button) {
    button.addEventListener("click", function () {
      try {
        localStorage.setItem(KEY, "1");
      } catch (e) {
        /* ignore */
      }
      box.hidden = true;
    });
  }
})();
