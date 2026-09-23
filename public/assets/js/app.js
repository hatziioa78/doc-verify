(() => {
    const sidebar = document.querySelector("[data-sidebar]");
    const toggle = document.querySelector("[data-sidebar-toggle]");
    const backdrop = document.querySelector("[data-backdrop]");
    const closeNav = () => sidebar?.classList.remove("is-open");
    toggle?.addEventListener("click", () => sidebar?.classList.add("is-open"));
    backdrop?.addEventListener("click", closeNav);
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeNav();
        }
    });

    document.querySelectorAll("form[data-loading]").forEach((form) => {
        form.addEventListener("submit", () => {
            const button = form.querySelector("[type='submit']");
            if (!button || button.dataset.busy === "1") {
                return;
            }
            button.dataset.busy = "1";
            button.textContent = button.getAttribute("data-loading-text") || "Παρακαλώ περιμένετε…";
            button.disabled = true;
        });
    });

    document.querySelectorAll("[data-copy]").forEach((button) => {
        button.addEventListener("click", async () => {
            const target = document.querySelector(button.getAttribute("data-copy") || "");
            const value = target?.value || target?.textContent || "";
            const previous = button.textContent;
            try {
                await navigator.clipboard.writeText(value.trim());
                button.textContent = "Αντιγράφηκε";
            } catch (error) {
                target?.focus();
                target?.select?.();
                button.textContent = "Επιλέχθηκε";
            }
            window.setTimeout(() => {
                button.textContent = previous;
            }, 1600);
        });
    });

    document.querySelectorAll("[data-password-toggle]").forEach((button) => {
        button.addEventListener("click", () => {
            const input = document.getElementById(button.getAttribute("data-password-toggle") || "");
            if (!input) {
                return;
            }
            const visible = input.type === "text";
            input.type = visible ? "password" : "text";
            button.textContent = visible ? "Εμφάνιση" : "Απόκρυψη";
        });
    });

    const roleSelect = document.querySelector("#role");
    const userOnly = document.querySelector("[data-user-only]");
    const syncRole = () => {
        if (!roleSelect || !userOnly) {
            return;
        }
        userOnly.hidden = roleSelect.value !== "user";
    };
    roleSelect?.addEventListener("change", syncRole);
    syncRole();

    document.querySelector("[data-add-network]")?.addEventListener("click", () => {
        const list = document.querySelector("[data-network-list]");
        const row = list?.querySelector(".network-row");
        if (!list || !row) {
            return;
        }
        const copy = row.cloneNode(true);
        copy.querySelectorAll("input").forEach((input) => {
            input.value = "";
        });
        list.appendChild(copy);
    });
})();
