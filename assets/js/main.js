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

  /* ---------- "Search" in the header jumps to the shop search box ---------- */
  const focusSearch = () => {
    if (location.hash !== "#search") return;
    const input = document.getElementById("search");
    if (input) input.focus();
  };
  focusSearch();
  window.addEventListener("hashchange", focusSearch);
});
