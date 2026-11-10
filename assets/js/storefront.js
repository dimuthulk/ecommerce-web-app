/**
 * Storefront forms -> JSON API (uses window.api from api.js).
 *
 * A form opts in with  data-api-form="<name>"  (see the handlers below). If this file does not load,
 * the forms still post to their PHP pages exactly as before.
 *
 *   add-to-bag   product.php            POST  /cart/items
 *   cart-line    cart.php               PATCH / DELETE  /cart/items
 *   login        login.php              POST  /auth/login
 *   register     register.php           POST  /auth/register
 *   forgot       forgot-password.php    POST  /auth/forgot
 *   reset-link   account.php            POST  /auth/forgot   (the "Request password reset" button)
 *   contact      contact.php            POST  /contact
 *   profile      account.php            PATCH /account/profile
 *   address      account.php            PATCH /account/address
 *   checkout     checkout.php           POST  /checkout
 */
document.addEventListener("DOMContentLoaded", () => {
  const api = window.api;
  if (!api) return;

  const FLASH_KEY = "storefront-flash";

  /* ---------- Small helpers ---------- */

  const toast = (options) => {
    if (typeof window.showToast === "function") window.showToast(options);
  };

  // A toast to show on the next page (used right before a redirect)
  const flash = (options) => {
    try {
      sessionStorage.setItem(FLASH_KEY, JSON.stringify(options));
    } catch (e) {
      /* storage blocked: the redirect still works, just without the toast */
    }
  };

  try {
    const saved = sessionStorage.getItem(FLASH_KEY);
    if (saved) {
      sessionStorage.removeItem(FLASH_KEY);
      toast(JSON.parse(saved));
    }
  } catch (e) {
    /* ignore */
  }

  const go = (url) => window.location.assign(url);

  const setCartCount = (count) => {
    document.querySelectorAll("[data-cart-count]").forEach((node) => {
      node.textContent = String(count);
    });
  };

  // Login / register start a fresh session token: keep the client in step
  const syncCsrf = (data) => {
    if (!data || !data.csrf) return;
    api.setCsrf(data.csrf);
    const tag = document.querySelector('meta[name="csrf-token"]');
    if (tag) tag.content = data.csrf;
  };

  const formData = (form) => {
    const data = {};
    new FormData(form).forEach((value, key) => {
      if (key === "csrf" || key === "form") return; // csrf travels in the header; "form" only told PHP which form it was
      data[key] = value;
    });
    return data;
  };

  /* ---------- Errors shown on the form ---------- */

  const clearErrors = (form) => {
    form.querySelectorAll(".field-error").forEach((node) => node.remove());
    form.querySelectorAll("[aria-invalid]").forEach((node) => {
      node.removeAttribute("aria-invalid");
      node.removeAttribute("aria-describedby");
    });
    document.querySelectorAll("[data-api-alert]").forEach((node) => node.remove());
  };

  const showAlert = (form, message) => {
    const alert = document.createElement("p");
    alert.className = "alert alert--error";
    alert.setAttribute("role", "alert");
    alert.dataset.apiAlert = "";
    alert.textContent = message;
    form.before(alert);
  };

  const showErrors = (form, error, { alwaysAlert = false } = {}) => {
    let shown = 0;
    let firstInput = null;

    Object.entries(error.fields || {}).forEach(([name, message]) => {
      if (!message) return;
      const input = form.querySelector('[name="' + name + '"]');
      if (!input) return;

      const note = document.createElement("p");
      note.className = "field-error";
      note.id = "err-" + name;
      note.textContent = message;

      input.setAttribute("aria-invalid", "true");
      input.setAttribute("aria-describedby", note.id);

      const checkbox = input.closest(".check");
      if (checkbox) checkbox.after(note);
      else (input.closest(".field") || input.parentElement).append(note);

      shown++;
      if (!firstInput) firstInput = input;
    });

    if (shown === 0 || error.code !== "validation_failed" || alwaysAlert) {
      showAlert(form, shown > 0 && error.code === "validation_failed" ? "Please check the highlighted fields." : error.message);
    }
    if (firstInput) firstInput.focus();
  };

  /* ---------- Cart page: swap in the fresh server-rendered bag ---------- */

  const refreshPage = async () => {
    try {
      const response = await fetch(window.location.href, {
        credentials: "same-origin",
        headers: { Accept: "text/html" },
      });
      const doc = new DOMParser().parseFromString(await response.text(), "text/html");
      const next = doc.querySelector(".container.page-top");
      const current = document.querySelector(".container.page-top");
      if (!response.ok || !next || !current) throw new Error("no page");

      current.replaceWith(next);
      const count = doc.querySelector("[data-cart-count]");
      if (count) setCartCount(count.textContent);
    } catch (e) {
      window.location.reload();
    }
  };

  // Typing a new quantity saves it (Enter or leaving the box)
  document.addEventListener("change", (event) => {
    const input = event.target;
    if (!input.matches || !input.matches(".cart-line__controls .qty__input")) return;
    const form = input.form;
    if (!form || form.dataset.busy) return;
    const save = form.querySelector('button[name="action"][value="set"]');
    if (form.requestSubmit) form.requestSubmit(save);
    else form.submit();
  });

  /* ---------- What each form does ---------- */

  const handlers = {
    "add-to-bag": {
      errors: "toast",
      run: (form, data) =>
        api.post("/cart/items", { slug: form.dataset.slug, color: data.color, size: data.size, qty: data.qty }),
      done: ({ data }, form) => {
        setCartCount(data.cart.totals.items);

        const view = { actionLabel: "View bag", actionUrl: form.dataset.cartUrl };
        const what = data.name + " \u00b7 " + data.label;

        if (data.notice === "at_maximum") {
          toast({ title: "Already at the maximum", text: "Only " + data.available + " available in " + data.label + ".", type: "info", ...view });
        } else if (data.notice === "limited_by_stock") {
          toast({ title: "Added to bag", text: what + " \u00b7 only " + data.available + " available", type: "success", ...view });
        } else {
          toast({ title: "Added to bag", text: what + (data.added > 1 ? " \u00b7 Qty " + data.added : ""), type: "success", ...view });
        }

        if (form.elements.qty) form.elements.qty.value = 1;
      },
    },

    "cart-line": {
      errors: "toast",
      run: (form, data, submitter) => {
        const action = (submitter && submitter.value) || "set";
        if (action === "remove") return api.del("/cart/items", { key: data.key });

        const current = parseInt(form.elements.qty.defaultValue, 10) || 1;
        let qty = current;
        if (action === "inc") qty = current + 1;
        else if (action === "dec") qty = current - 1;
        else qty = parseInt(data.qty, 10) || current;

        return api.patch("/cart/items", { key: data.key, qty: Math.max(1, qty) });
      },
      done: async ({ data }, form) => {
        await refreshPage();
        if (data.notice === "limited_by_stock") {
          const line = data.cart.items.find((item) => item.key === form.elements.key.value);
          toast({
            title: "Quantity updated",
            text: line ? "Only " + line.available + " available in " + line.color + " / " + line.size + "." : "Not enough stock for that quantity.",
            type: "info",
          });
        }
      },
      failed: (error) => {
        if (error.status === 404) refreshPage(); // the item is already gone: show the real bag
      },
    },

    login: {
      run: (form, data) => api.post("/auth/login", data),
      done: ({ data }) => {
        syncCsrf(data);
        flash({ title: "Welcome back, " + data.user.first_name, type: "success" });
        go(data.redirect);
      },
    },

    register: {
      run: (form, data) => api.post("/auth/register", data),
      done: ({ data }) => {
        syncCsrf(data);
        flash({ title: "Welcome, " + data.user.first_name, text: "Your account is ready.", type: "success" });
        go(data.redirect);
      },
    },

    forgot: {
      run: (form, data) => api.post("/auth/forgot", data),
      done: ({ data }, form) => {
        const host = form.closest(".auth-main") || form.parentElement;
        const make = (tag, className, text) => {
          const node = document.createElement(tag);
          if (className) node.className = className;
          if (text) node.textContent = text;
          return node;
        };

        const title = make("h1", "", "Check your inbox.");

        const lead = make("p", "auth-lead");
        const strong = make("strong", "", form.elements.email.value.trim());
        lead.append(
          "If an account exists for ",
          strong,
          ", we have sent a secure link to reset the password. It can be used once and expires in " + (form.dataset.resetMinutes || "a few") + " minutes.",
        );

        const note = make("p", "auth-note", "Nothing arrived? Check your spam folder, or ");
        const again = make("a", "", "request another link");
        again.href = form.dataset.forgotUrl || window.location.href;
        note.append(again, " after a minute.");

        const parts = [title, lead, note];

        if (data.dev_link) {
          const dev = make("p", "alert");
          dev.setAttribute("role", "status");
          const link = make("a", "", data.dev_link);
          link.href = data.dev_link;
          dev.append(make("strong", "", "Development only."), " E-mail is not sent from localhost, so here is the link (also saved in storage/mail.log):", document.createElement("br"), link);
          parts.push(dev);
        }

        const actions = make("div", "auth-actions");
        const back = make("a", "btn btn--ghost btn--block", "Back to sign in");
        back.href = form.dataset.loginUrl || "/login";
        actions.append(back);
        parts.push(actions);

        host.replaceChildren(...parts);
      },
    },

    "reset-link": {
      errors: "toast",
      run: (form, data) => api.post("/auth/forgot", data),
      done: ({ data }) => {
        toast({
          title: "Check your inbox",
          text: "If an account exists for this email, we have sent a secure reset link.",
          type: "success",
          ...(data.dev_link ? { actionLabel: "Open link", actionUrl: data.dev_link, timeout: 0 } : {}),
        });
      },
    },

    contact: {
      run: (form, data) => api.post("/contact", data),
      done: (result, form) => {
        toast({ title: "Message sent", text: "Thank you. We will get back to you soon.", type: "success" });
        ["message", "order_ref"].forEach((name) => {
          if (form.elements[name]) form.elements[name].value = "";
        });
      },
    },

    profile: {
      run: (form, data) => api.patch("/account/profile", data),
      done: () => toast({ title: "Profile saved", type: "success" }),
    },

    address: {
      run: (form, data) => api.patch("/account/address", data),
      done: () => toast({ title: "Address saved", type: "success" }),
    },

    checkout: {
      alwaysAlert: true,
      run: (form, data) => api.post("/checkout", data),
      done: ({ data }) => go(data.redirect),
    },
  };

  /* ---------- One submit listener for every opted-in form ---------- */

  document.addEventListener("submit", async (event) => {
    const form = event.target;
    if (!form.matches || !form.matches("form[data-api-form]")) return;

    const handler = handlers[form.dataset.apiForm];
    if (!handler) return; // unknown name: let the normal PHP post happen

    event.preventDefault();
    if (form.dataset.busy) return;

    form.dataset.busy = "1";
    clearErrors(form);
    const buttons = [...form.querySelectorAll('button[type="submit"]:not(:disabled)')];
    buttons.forEach((button) => {
      button.disabled = true;
    });

    let redirecting = false;
    try {
      const result = await handler.run(form, formData(form), event.submitter);
      redirecting = Boolean(result && result.data && result.data.redirect);
      await handler.done(result, form);
    } catch (error) {
      if (!(error instanceof api.ApiError)) {
        console.error(error);
        error = new api.ApiError(0, "client_error", "Something went wrong. Please try again.");
      }
      if (handler.errors === "toast") toast({ title: error.message, type: "error" });
      else showErrors(form, error, { alwaysAlert: handler.alwaysAlert });
      if (handler.failed) handler.failed(error);
    } finally {
      delete form.dataset.busy;
      // Keep the button off while the browser is leaving for the next page
      if (!redirecting) {
        buttons.forEach((button) => {
          if (button.isConnected) button.disabled = false;
        });
      }
    }
  });
});
