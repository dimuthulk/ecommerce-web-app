document.addEventListener("DOMContentLoaded", () => {
  /* ---------- Mobile / tablet menu ---------- */
  const navToggle = document.querySelector(".nav-toggle");
  const nav = document.getElementById("site-nav");

  if (navToggle && nav) {
    const setNavOpen = (open) => {
      nav.classList.toggle("is-open", open);
      navToggle.setAttribute("aria-expanded", String(open));
      navToggle.setAttribute("aria-label", open ? "Close menu" : "Menu");
    };

    navToggle.addEventListener("click", () =>
      setNavOpen(!nav.classList.contains("is-open")),
    );

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") setNavOpen(false);
    });

    // Reset when the window grows past the tablet breakpoint
    window
      .matchMedia("(min-width: 1025px)")
      .addEventListener("change", (event) => {
        if (event.matches) setNavOpen(false);
      });
  }

  /* ---------- Shop: filters ---------- */
  const filterForm = document.querySelector("form[data-autosubmit]");

  if (filterForm) {
    const submitForm = () =>
      filterForm.requestSubmit
        ? filterForm.requestSubmit()
        : filterForm.submit();
    const allCategories = filterForm.querySelector('[data-all-for="category"]');
    const categoryBoxes = () =>
      filterForm.querySelectorAll('input[name="category[]"]');

    // Keep URLs clean: don't send an empty search or the default sort
    filterForm.addEventListener("submit", () => {
      const search = filterForm.elements.q;
      const sort = filterForm.elements.sort;
      if (search && !search.value.trim()) search.disabled = true;
      if (sort && sort.value === "recommended") sort.disabled = true;
    });
    // ...and undo that when the user comes back with the browser's Back button
    window.addEventListener("pageshow", () => {
      ["q", "sort"].forEach((name) => {
        if (filterForm.elements[name])
          filterForm.elements[name].disabled = false;
      });
    });

    // Apply filters as soon as a checkbox / the sort menu changes (search applies on Enter)
    filterForm.addEventListener("change", (event) => {
      const target = event.target;
      if (target.name === "q") return;

      if (target === allCategories) {
        categoryBoxes().forEach((box) => {
          box.checked = false;
        });
        allCategories.checked = true;
      } else if (target.matches('input[name="category[]"]') && allCategories) {
        allCategories.checked = !filterForm.querySelector(
          'input[name="category[]"]:checked',
        );
      }
      submitForm();
    });

    // "Filters (n)" button opens / closes the filter panel on tablet + mobile
    const filtersToggle = filterForm.querySelector(".filters-toggle");
    const filtersPanel = document.getElementById("filters-panel");

    if (filtersToggle && filtersPanel) {
      filtersToggle.addEventListener("click", () => {
        const open = !filtersPanel.classList.contains("is-open");
        filtersPanel.classList.toggle("is-open", open);
        filtersToggle.setAttribute("aria-expanded", String(open));
      });
    }
  }

  /* ---------- Product page: gallery ---------- */
  const gallery = document.querySelector("[data-gallery]");

  if (gallery) {
    const mainFrame = gallery.querySelector(".gallery__frame--main");

    // Clicking a thumbnail swaps it with the large picture
    gallery.querySelectorAll(".gallery__thumb").forEach((thumb) => {
      thumb.addEventListener("click", () => {
        const thumbFrame = thumb.querySelector(".gallery__frame");

        [mainFrame.innerHTML, thumbFrame.innerHTML] = [
          thumbFrame.innerHTML,
          mainFrame.innerHTML,
        ];
        [mainFrame.dataset.label, thumb.dataset.label] = [
          thumb.dataset.label,
          mainFrame.dataset.label,
        ];
        thumb.setAttribute(
          "aria-label",
          "Show " + thumb.dataset.label.toLowerCase(),
        );
      });
    });
  }

  /* ---------- Product page: colour / size / quantity ---------- */
  const productForm = document.querySelector("[data-product-form]");

  if (productForm) {
    const stockData = JSON.parse(productForm.dataset.stock || "{}"); // { colour: { size: qty } }
    const pick = (selector) => productForm.querySelector(selector);

    const sizeInputs = [...productForm.querySelectorAll('input[name="size"]')];
    const qtyInput = pick("[data-qty]");
    const addButton = pick("[data-add]");

    const update = () => {
      const colorInput = pick('input[name="color"]:checked');
      const color = colorInput ? colorInput.value : "";
      const stock = stockData[color] || {};

      // 1. sold-out sizes for this colour
      const soldOut = [];
      sizeInputs.forEach((input) => {
        const sold = (stock[input.value] || 0) < 1;
        input.disabled = sold;
        input.closest(".size-option").classList.toggle("is-soldout", sold);
        input.nextElementSibling.textContent = sold
          ? input.value + " \u00b7 Sold out"
          : input.value;
        if (sold) soldOut.push(input.value);
        if (sold && input.checked) input.checked = false;
      });

      // 2. keep the chosen size, or fall back to the first one in stock
      let sizeInput = pick('input[name="size"]:checked');
      if (!sizeInput) {
        sizeInput =
          sizeInputs.find((input) => !input.disabled && input.value === "M") ||
          sizeInputs.find((input) => !input.disabled) ||
          null;
        if (sizeInput) sizeInput.checked = true;
      }
      const size = sizeInput ? sizeInput.value : "";
      const available = size ? stock[size] || 0 : 0;

      // 3. texts
      pick("[data-selected-color]").textContent = color;
      pick("[data-selected-size]").textContent = size || "Select";

      const note = pick("[data-soldout-note]");
      if (soldOut.length === 0) {
        note.textContent = "";
      } else if (soldOut.length === sizeInputs.length) {
        note.textContent = color + " is sold out. Try another color.";
      } else {
        note.textContent =
          soldOut.join(", ") +
          " / " +
          color +
          " is sold out. Select an available size.";
      }
      note.hidden = soldOut.length === 0;

      const badge = pick("[data-badge]");
      badge.textContent = available > 0 ? "In stock" : "Sold out";
      badge.classList.toggle("stock-badge--out", available < 1);

      pick("[data-availability]").textContent = size
        ? color + " / " + size + " \u00b7 " + available + " available"
        : color + " is sold out";

      // 4. quantity + button
      qtyInput.max = Math.max(1, available);
      clampQty();
      addButton.disabled = available < 1;
    };

    const clampQty = () => {
      const max = parseInt(qtyInput.max, 10) || 1;
      let value = parseInt(qtyInput.value, 10);
      if (Number.isNaN(value) || value < 1) value = 1;
      qtyInput.value = Math.min(value, max);
    };

    productForm.addEventListener("change", (event) => {
      if (event.target.name === "color" || event.target.name === "size")
        update();
    });
    qtyInput.addEventListener("change", clampQty);
    pick("[data-qty-minus]").addEventListener("click", () => {
      qtyInput.value = (parseInt(qtyInput.value, 10) || 1) - 1;
      clampQty();
    });
    pick("[data-qty-plus]").addEventListener("click", () => {
      qtyInput.value = (parseInt(qtyInput.value, 10) || 1) + 1;
      clampQty();
    });

    update();
  }

  /* ---------- Toasts ---------- */
  const toastRegion = document.getElementById("toast-region");

  if (toastRegion) {
    const dismissToast = (toast) => {
      if (!toast || toast.classList.contains("is-leaving")) return;
      toast.classList.add("is-leaving");
      toast.addEventListener("animationend", () => toast.remove(), {
        once: true,
      });
      setTimeout(() => toast.remove(), 400); // safety net
    };

    // Fade away after a few seconds; the clock stops while the mouse or keyboard is on the toast
    const armToast = (toast) => {
      let remaining = parseInt(toast.dataset.timeout || "5000", 10);
      if (!remaining) return; // 0 = stay until closed

      let timer = null;
      let startedAt = 0;
      let paused = false;

      const start = () => {
        paused = false;
        clearTimeout(timer);
        startedAt = Date.now();
        timer = setTimeout(() => dismissToast(toast), remaining);
      };
      const pause = () => {
        if (paused) return;
        paused = true;
        clearTimeout(timer);
        remaining = Math.max(1500, remaining - (Date.now() - startedAt));
      };

      toast.addEventListener("mouseenter", pause);
      toast.addEventListener("mouseleave", () => {
        if (!toast.contains(document.activeElement)) start();
      });
      toast.addEventListener("focusin", pause);
      toast.addEventListener("focusout", () => {
        if (!toast.matches(":hover")) start();
      });
      start();
    };

    toastRegion.querySelectorAll("[data-toast]").forEach(armToast);

    toastRegion.addEventListener("click", (event) => {
      const closeButton = event.target.closest("[data-toast-close]");
      if (closeButton) dismissToast(closeButton.closest("[data-toast]"));
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape")
        toastRegion.querySelectorAll("[data-toast]").forEach(dismissToast);
    });

    // showToast({ title: 'Saved', text: 'Optional second line', type: 'success' | 'error' | 'info',
    //             actionLabel: 'Undo', actionUrl: '/somewhere', timeout: 5000 })
    const toastIcons = {
      success: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
      error: '<path d="M12 6.5v7M12 17.5h.01"/>',
      info: '<path d="M12 10.5v7M12 6.5h.01"/>',
    };
    const toastTimeouts = { success: 5000, info: 6000, error: 8000 };

    window.showToast = ({
      title,
      text = "",
      type = "info",
      actionLabel = "",
      actionUrl = "",
      timeout,
    } = {}) => {
      if (!title) return;
      if (!toastIcons[type]) type = "info";

      const toast = document.createElement("div");
      toast.className = "toast toast--" + type;
      toast.setAttribute("role", type === "error" ? "alert" : "status");
      toast.dataset.toast = "";
      toast.dataset.timeout = String(timeout ?? toastTimeouts[type]);

      const icon = document.createElement("span");
      icon.className = "toast__icon";
      icon.setAttribute("aria-hidden", "true");
      icon.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" ' +
        'stroke-linecap="round" stroke-linejoin="round">' +
        toastIcons[type] +
        "</svg>"; // fixed markup, no user text

      const body = document.createElement("div");
      body.className = "toast__body";
      const heading = document.createElement("p");
      heading.className = "toast__title";
      heading.textContent = title;
      body.append(heading);
      if (text) {
        const line = document.createElement("p");
        line.className = "toast__text";
        line.textContent = text;
        body.append(line);
      }
      toast.append(icon, body);

      if (actionLabel && actionUrl && !/^\s*javascript:/i.test(actionUrl)) {
        const link = document.createElement("a");
        link.className = "toast__action";
        link.href = actionUrl;
        link.textContent = actionLabel;
        toast.append(link);
      }

      const close = document.createElement("button");
      close.type = "button";
      close.className = "toast__close";
      close.setAttribute("aria-label", "Dismiss notification");
      close.dataset.toastClose = "";
      close.textContent = "\u00d7";
      toast.append(close);

      toastRegion.append(toast);
      while (toastRegion.children.length > 3)
        toastRegion.firstElementChild.remove(); // keep the screen tidy
      armToast(toast);
    };
  }

  /* ---------- Cart: quantity box saves when changed ---------- */
  document
    .querySelectorAll(".cart-line__controls .qty__input")
    .forEach((input) => {
      input.addEventListener("change", () => {
        const form = input.form;
        const saveButton = form.querySelector(
          'button[name="action"][value="set"]',
        );
        if (form.requestSubmit) form.requestSubmit(saveButton);
        else form.submit();
      });
    });

  /* ---------- "Search" in the header jumps to the shop search box ---------- */
  const focusSearch = () => {
    if (location.hash !== "#search") return;
    const input = document.getElementById("search");
    if (input) input.focus();
  };
  focusSearch();
  window.addEventListener("hashchange", focusSearch);
});
