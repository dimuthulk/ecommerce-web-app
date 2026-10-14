document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.querySelector(".nav-toggle");
  const nav = document.getElementById("site-nav");
  if (!toggle || !nav) return;

  const setOpen = (open) => {
    nav.classList.toggle("is-open", open);
    toggle.setAttribute("aria-expanded", String(open));
    toggle.textContent = open ? "Close" : "Menu";
  };

  toggle.addEventListener("click", () =>
    setOpen(!nav.classList.contains("is-open")),
  );

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") setOpen(false);
  });

  window
    .matchMedia("(min-width: 769px)")
    .addEventListener("change", (event) => {
      if (event.matches) setOpen(false);
    });
});
