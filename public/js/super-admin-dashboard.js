(function () {
    "use strict";

    document
        .querySelectorAll("[data-dashboard-chart]")
        .forEach(function (section) {
            if (section.dataset.initialized) return;
            section.dataset.initialized = "true";
            const period = section.querySelector("[data-chart-period]");
            const currency = section.querySelector("[data-chart-currency]");
            const content = section.querySelector("[data-chart-content]");
            const status = section.querySelector("[data-chart-status]");
            let controller;
            let sequence = 0;
            let pendingKey = null;
            let rendered = { period: period.value, currency: currency?.value };

            async function refresh() {
                const selected = {
                    period: period.value,
                    currency: currency?.value,
                };
                const key = JSON.stringify(selected);
                if (pendingKey === key) return;
                pendingKey = key;
                const request = ++sequence;
                controller?.abort();
                controller = new AbortController();
                section.setAttribute("aria-busy", "true");
                status.hidden = false;
                status.textContent = "Loading…";
                const params = new URLSearchParams({
                    section: section.dataset.dashboardChart,
                    period: selected.period,
                });
                if (selected.currency)
                    params.set("currency", selected.currency);
                try {
                    const response = await fetch(
                        section.dataset.url + "?" + params.toString(),
                        {
                            signal: controller.signal,
                            credentials: "same-origin",
                            headers: {
                                Accept: "application/json",
                                "X-Requested-With": "XMLHttpRequest",
                            },
                        },
                    );
                    if (!response.ok) throw new Error("Chart request failed");
                    const data = await response.json();
                    if (typeof data.html !== "string")
                        throw new Error("Invalid chart response");
                    if (request !== sequence) return;
                    content.innerHTML = data.html;
                    rendered = selected;
                    status.hidden = true;
                    status.textContent = "";
                } catch (error) {
                    if (request !== sequence) return;
                    period.value = rendered.period;
                    if (currency) currency.value = rendered.currency;
                    if (window.jQuery?.fn?.select2) {
                        window.jQuery(period).trigger("change.select2");
                        if (currency)
                            window.jQuery(currency).trigger("change.select2");
                    }
                    status.textContent =
                        "Unable to update this chart. Previous results are shown; select a period to retry.";
                } finally {
                    if (request === sequence) {
                        pendingKey = null;
                        section.setAttribute("aria-busy", "false");
                    }
                }
            }

            period.addEventListener("change", refresh);
            period.addEventListener("input", refresh);
            currency?.addEventListener("change", refresh);
            currency?.addEventListener("input", refresh);
        });
})();
