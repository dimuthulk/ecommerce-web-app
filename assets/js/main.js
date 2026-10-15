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

  /* ---------- "Search" in the header jumps to the shop search box ---------- */
  const focusSearch = () => {
    if (location.hash !== "#search") return;
    const input = document.getElementById("search");
    if (input) input.focus();
  };
  focusSearch();
  window.addEventListener("hashchange", focusSearch);
});
