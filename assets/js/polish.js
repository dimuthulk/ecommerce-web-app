/**
 * Polish layer: pre-loader, page transitions, image skeletons, scroll reveal, loading states.
 * Purely visual: if this file fails to load, the site works exactly as before
 * (the pre-loader also hides itself after 5s from the script in components/header.php).
 * Pairs with assets/css/polish.css.
 */
(() => {
  "use strict";

  const root = document.documentElement;
  const reduce =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- Pre-loader ---------- */
  const preloader = document.getElementById("preloader");

  const hidePreloader = () => {
    root.classList.remove("is-loading");
    if (!preloader) return;
    preloader.classList.add("is-done");
    setTimeout(() => preloader.remove(), 800);
  };

  if (root.classList.contains("is-loading")) {
    const MIN_MS = reduce ? 0 : 650; // long enough to be seen, short enough not to annoy
    const finish = () =>
      setTimeout(hidePreloader, Math.max(0, MIN_MS - performance.now()));
    if (document.readyState === "complete") finish();
    else window.addEventListener("load", finish, { once: true });
  }

  // Back / forward from the browser cache: undo any "leaving" or "filtering" state
  window.addEventListener("pageshow", (event) => {
    document.body.classList.remove("is-leaving");
    document
      .querySelectorAll(".product-grid.is-filtering")
      .forEach((grid) => grid.classList.remove("is-filtering"));
    if (event.persisted) hidePreloader();
  });

  /* ---------- Page transition: fade out when leaving through an internal link ---------- */
  document.addEventListener("click", (event) => {
    if (
      reduce ||
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    )
      return;

    const link = event.target.closest && event.target.closest("a[href]");
    if (
      !link ||
      (link.target && link.target !== "_self") ||
      link.hasAttribute("download")
    )
      return;

    let url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (e) {
      return;
    }
    if (url.origin !== window.location.origin) return; // other sites, mailto:, tel:
    if (
      url.pathname === window.location.pathname &&
      url.search === window.location.search
    )
      return; // same page or #anchor jump
    if (url.pathname.indexOf("/api/") !== -1) return; // downloads / JSON

    document.body.classList.add("is-leaving");
    setTimeout(() => document.body.classList.remove("is-leaving"), 2500); // safety net
  });

  /* ---------- Header shadow once the page scrolls ---------- */
  const header = document.querySelector(".site-header");
  if (header) {
    const onScroll = () =>
      header.classList.toggle("is-scrolled", window.scrollY > 8);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  /* ---------- Image skeletons: shimmer until the picture has loaded ---------- */
  const SKELETON_HOLDERS =
    ".product-card__media, .cart-line__image, .gallery__frame";

  document.querySelectorAll(".site-main img").forEach((img) => {
    if (img.complete) return;
    const holder = img.closest(SKELETON_HOLDERS);
    const done = () => {
      img.classList.remove("img-pending");
      if (holder) holder.classList.remove("skel");
    };
    img.classList.add("img-pending");
    if (holder) holder.classList.add("skel");
    img.addEventListener("load", done, { once: true });
    img.addEventListener("error", done, { once: true });
    setTimeout(done, 8000); // never leave a skeleton hanging
  });

  /* ---------- Scroll reveal (only for things below the fold) ---------- */
  if (!reduce && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          const el = entry.target;
          observer.unobserve(el);
          el.classList.add("is-visible");
          // drop the classes afterwards so hover effects and layout are untouched
          setTimeout(() => el.classList.remove("reveal", "is-visible"), 1000);
        });
      },
      { rootMargin: "0px 0px -8% 0px", threshold: 0.08 },
    );

    document
      .querySelectorAll(
        ".site-main .product-card, .site-main .promo-card, .site-main .brand-strip, .site-main .section-head",
      )
      .forEach((el) => {
        if (el.getBoundingClientRect().top < window.innerHeight) return; // already on screen
        const index = el.parentElement
          ? Array.prototype.indexOf.call(el.parentElement.children, el)
          : 0;
        el.style.setProperty("--reveal-delay", (index % 4) * 70 + "ms");
        el.classList.add("reveal");
        observer.observe(el);
      });
  }

  /* ---------- Button spinner + cart line shimmer while a form is being sent ---------- */
  document.addEventListener(
    "submit",
    (event) => {
      const form = event.target;
      if (!form.matches) return;

      // Shop filters / sort: the page reloads, so show skeleton tiles meanwhile
      if (form.matches("form[data-autosubmit]")) {
        document
          .querySelectorAll(".product-grid")
          .forEach((grid) => grid.classList.add("is-filtering"));
        return;
      }

      if (!form.matches("form[data-api-form]")) return;

      const button =
        event.submitter || form.querySelector('button[type="submit"]');
      const line = form.closest(".cart-line");
      if (button) button.classList.add("is-loading");
      if (line) line.classList.add("is-updating");

      // storefront.js sets data-busy while the request runs and removes it afterwards
      const watcher = new MutationObserver(() => {
        if (form.dataset.busy) return;
        watcher.disconnect();
        if (line) line.classList.remove("is-updating");
        // a button that is still disabled means the browser is redirecting: keep the spinner
        if (button && !button.disabled) button.classList.remove("is-loading");
      });
      watcher.observe(form, {
        attributes: true,
        attributeFilter: ["data-busy"],
      });
    },
    true,
  );

  /* ---------- Bag badge: small pop when the count changes ---------- */
  document.querySelectorAll("[data-cart-count]").forEach((badge) => {
    let last = badge.textContent;
    new MutationObserver(() => {
      if (badge.textContent === last) return;
      last = badge.textContent;
      badge.classList.remove("is-bump");
      void badge.offsetWidth; // restart the animation
      badge.classList.add("is-bump");
    }).observe(badge, { childList: true, characterData: true, subtree: true });
    badge.addEventListener("animationend", () =>
      badge.classList.remove("is-bump"),
    );
  });

  /* ---------- Cart: soft fade when storefront.js swaps in the refreshed bag ---------- */
  const main = document.querySelector(".site-main");
  if (main) {
    new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType !== 1 || !node.classList.contains("page-top"))
            return;
          node.classList.add("is-refreshed");
          setTimeout(() => node.classList.remove("is-refreshed"), 500);
        });
      });
    }).observe(main, { childList: true });
  }
})();
