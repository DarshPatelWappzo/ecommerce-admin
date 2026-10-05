(() => {
    const storageKey = "sidebar-collapsed";
    const toggle = document.querySelector("[data-sidebar-toggle]");
    const desktop = window.matchMedia("(min-width: 992px)");

    if (localStorage.getItem(storageKey) === "true") {
        document.body.classList.add("sidebar-collapsed");
    }

    const tooltips = [
        ...document.querySelectorAll(".sidebar-link[data-tooltip]"),
    ].map((link) => {
        link.addEventListener("show.bs.tooltip", (event) => {
            if (
                !desktop.matches ||
                !document.body.classList.contains("sidebar-collapsed")
            ) {
                event.preventDefault();
            }
        });

        return new bootstrap.Tooltip(link, {
            title: link.dataset.tooltip,
            container: "body",
            boundary: document.body,
            placement: "right",
            offset: [0, 12],
            customClass: "sidebar-tooltip",
            animation: false,
        });
    });

    const hideTooltips = () => tooltips.forEach((tooltip) => tooltip.hide());
    const updateToggle = () => {
        const isCollapsed =
            document.body.classList.contains("sidebar-collapsed");
        toggle?.setAttribute("aria-expanded", String(!isCollapsed));
        toggle?.setAttribute(
            "aria-label",
            isCollapsed ? "Expand sidebar" : "Collapse sidebar",
        );
    };

    updateToggle();
    toggle?.addEventListener("click", () => {
        const isCollapsed = document.body.classList.toggle("sidebar-collapsed");
        localStorage.setItem(storageKey, isCollapsed);
        hideTooltips();
        updateToggle();
    });

    document
        .querySelector(".sidebar-inner")
        ?.addEventListener("scroll", hideTooltips, { passive: true });
    desktop.addEventListener("change", hideTooltips);
})();
