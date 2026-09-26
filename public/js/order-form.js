jQuery(() => {
    "use strict";
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    async function send(url, payload, method = "POST", signal) {
        const response = await fetch(url, {
            method,
            signal,
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrf,
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(payload),
        });
        const data = await response
            .json()
            .catch(() => ({
                message:
                    "The server could not complete this request. Please retry.",
            }));
        if (!response.ok) {
            throw data;
        }
        return data;
    }
    function showErrors(form, error) {
        const box = form.querySelector("[data-order-errors]");
        box.replaceChildren();
        const messages = Object.values(error.errors || {}).flat();
        (messages.length
            ? messages
            : [
                  error.message ||
                      "The request could not be completed. Please retry.",
              ]
        ).forEach((message) => {
            const line = document.createElement("div");
            line.textContent = message;
            box.append(line);
        });
        box.classList.remove("d-none");
        const fields = {};
        Object.entries(error.errors || {}).forEach(([key, messages]) => {
            const parts = key.split(".");
            const name =
                parts.shift() + parts.map((part) => `[${part}]`).join("");
            if (form.elements.namedItem(name)) {
                fields[name] = messages[0];
            }
        });
        $(form).data("validator")?.showErrors(fields);
        box.scrollIntoView({ block: "center", behavior: "smooth" });
    }
    async function save(form, payload, method = "POST") {
        form.dataset.busy = "true";
        const buttons = Array.from(
            form.querySelectorAll('button[type="submit"], button:not([type])'),
        );
        buttons.forEach((button) => {
            button.disabled = true;
        });
        form.querySelector("[data-order-errors]").classList.add("d-none");
        try {
            const result = await send(form.action, payload, method);
            window.location.assign(result.redirect);
        } catch (error) {
            showErrors(form, error);
            form.dataset.busy = "false";
            buttons.forEach((button) => {
                button.disabled = false;
            });
        }
    }
    document.querySelectorAll("[data-order-action]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            event.preventDefault();
            if (form.dataset.busy === "true" || !$(form).valid()) {
                return;
            }
            save(form, Object.fromEntries(new FormData(form)));
        });
    });

    const form = document.querySelector("[data-order-form]");
    if (!form) {
        return;
    }
    const config = window.orderFormConfig;
    const validator = $(form).data("validator");
    // $(form.elements.shipping_tax_id).rules("add", {
    //     required: () => Number(form.elements.shipping_amount.value) > 0,
    //     messages: { required: "Select a shipping tax when shipping is greater than zero." },
    // });
    $(form).on("change", "select[name]", function () {
        $(this).valid();
    });
    $(form.elements.gstin).on("input", function () {
        this.value = this.value.toUpperCase();
    });
    const tbody = form.querySelector("[data-order-items]");
    let sequence = 0;
    let previewTimer;
    let previewController;
    let previewVersion = 0;
    let pricingFingerprint = null;
    function remoteSelect(select, kind) {
        $(select).select2({
            width: "100%",
            placeholder:
                kind === "customers"
                    ? "Search customer or leave empty for guest"
                    : "Search product / SKU",
            allowClear: true,
            ajax: {
                url: config.optionsUrl,
                dataType: "json",
                delay: 250,
                data: (params) => ({
                    search: params.term || "",
                    page: params.page || 1,
                    kind,
                }),
                processResults: (data) => data,
            },
        });
    }
    const customer = form.elements.customer_id;
    const profileFields = [
        "customer_name",
        "customer_email",
        "customer_phone",
        "company_name",
        "gstin",
    ];
    remoteSelect(customer, "customers");
    function fillAddress(type, address) {
        if (!address) {
            return;
        }
        const values = {
            ...address,
            name: address.recipient_name,
            phone: [address.phone_country_code, address.phone]
                .filter(Boolean)
                .join(" "),
        };
        [
            "name",
            "phone",
            "address_line_1",
            "address_line_2",
            "city",
            "state_code",
            "country_code",
            "postal_code",
        ].forEach((key) => {
            const input = form.elements.namedItem(`${type}[${key}]`);
            input.value = values[key] || "";
            if (input.tagName === "SELECT") {
                $(input).trigger("change");
            }
        });
    }
    $(customer)
        .on("select2:select", (event) => {
            const selected = event.params.data;
            const profile = selected.profile;
            const values = [
                [profile.first_name, profile.last_name]
                    .filter(Boolean)
                    .join(" "),
                profile.email,
                [profile.phone_country_code, profile.phone]
                    .filter(Boolean)
                    .join(" "),
                profile.company_name,
                profile.gstin,
            ];
            profileFields.forEach((name, index) => {
                form.elements[name].value = values[index] || "";
                form.elements[name].readOnly = true;
            });
            fillAddress(
                "billing",
                selected.addresses.find(
                    (address) => address.is_default_billing,
                ) || selected.addresses[0],
            );
            fillAddress(
                "shipping",
                selected.addresses.find(
                    (address) => address.is_default_shipping,
                ) || selected.addresses[0],
            );
        })
        .on("select2:clear", () => {
            profileFields.forEach((name) => {
                form.elements[name].readOnly = false;
                form.elements[name].value = "";
            });
        });
    function shippingVisibility() {
        const hidden = form.elements.same_as_billing.checked;
        const container = form.querySelector("[data-shipping-address]");
        container.classList.toggle("d-none", hidden);
        container.querySelectorAll("input, select").forEach((input) => {
            input.disabled = hidden;
        });
        if (hidden) {
            container.querySelectorAll(".field-error").forEach((error) => error.remove());
        }
    }
    form.elements.same_as_billing.addEventListener(
        "change",
        shippingVisibility,
    );
    shippingVisibility();
    function addItem(item = {}) {
        const row = document.createElement("tr");
        row.dataset.row = sequence++;
        row.innerHTML =
            '<td><select class="form-select" data-variant data-select2-disabled><option value=""></option></select><input type="hidden" data-field="product_id"></td>' +
            '<td><input class="form-control" type="number" data-field="quantity" min="1" max="100000" step="1" value="1" aria-label="Quantity"></td>' +
            (config.permissions.price_override
                ? '<td><input class="form-control" type="number" data-field="unit_price" min="0" step="0.01" placeholder="Catalog price" aria-label="Price override"></td>'
                : "") +
            (config.permissions.discount
                ? '<td><input class="form-control" type="number" data-field="discount_amount" min="0" step="0.01" value="0" aria-label="Item discount"></td>'
                : "") +
            '<td data-line-tax>—</td><td data-line-total>—</td><td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-item aria-label="Remove item">Remove</button></td>';
        tbody.append(row);
        const select = row.querySelector("[data-variant]");
        const snapshot = config.snapshots.find(
            (snapshot) =>
                String(snapshot.product_variant_id) ===
                String(item.product_variant_id),
        );
        if (item.product_variant_id) {
            select.append(
                new Option(
                    snapshot
                        ? `${snapshot.product_name} — ${snapshot.sku}`
                        : `Variant #${item.product_variant_id}`,
                    item.product_variant_id,
                    true,
                    true,
                ),
            );
        }
        row.querySelectorAll("[data-field]").forEach((input) => {
            if (item[input.dataset.field] != null) {
                input.value = item[input.dataset.field];
            }
        });
        remoteSelect(select, "variants");
        $(select)
            .on("select2:select", (event) => {
                row.querySelector('[data-field="product_id"]').value =
                    event.params.data.product_id;
                const override = row.querySelector('[data-field="unit_price"]');
                if (override) {
                    override.value = "";
                }
                queuePreview();
            })
            .on("select2:clear", () => {
                row.querySelector('[data-field="product_id"]').value = "";
                queuePreview();
            });
        row.querySelector("[data-remove-item]").addEventListener(
            "click",
            () => {
                $(select).select2("destroy");
                row.remove();
                renameRows();
                queuePreview();
            },
        );
        renameRows();
    }
    function renameRows() {
        validator.resetForm();
        validator.arrayRulesCache = {};
        Array.from(tbody.rows).forEach((row, index) => {
            row.querySelector("[data-variant]").name =
                `items[${index}][product_variant_id]`;
            row.querySelectorAll("[data-field]").forEach((input) => {
                input.name = `items[${index}][${input.dataset.field}]`;
            });
        });
    }
    function itemPayload() {
        return Array.from(tbody.rows).map((row) => {
            const item = {
                product_variant_id: row.querySelector("[data-variant]").value,
            };
            row.querySelectorAll("[data-field]").forEach((input) => {
                if (
                    input.dataset.field === "unit_price" &&
                    input.value === ""
                ) {
                    return;
                }
                item[input.dataset.field] = input.value;
            });
            return item;
        });
    }
    function previewPayload() {
        return {
            items: itemPayload(),
            shipping_amount: form.elements.shipping_amount.value || "0",
            shipping_tax_id: form.elements.shipping_tax_id.value || null,
        };
    }
    function payload() {
        const data = {
            ...previewPayload(),
            same_as_billing: form.elements.same_as_billing.checked,
        };
        [
            "idempotency_key",
            "currency",
            "customer_id",
            ...profileFields,
            "payment_method",
            "customer_note",
            "internal_note",
        ].forEach((name) => {
            data[name] = form.elements[name].value || null;
        });
        ["billing", "shipping"].forEach((type) => {
            if (type === "shipping" && data.same_as_billing) {
                return;
            }
            data[type] = {};
            [
                "name",
                "phone",
                "address_line_1",
                "address_line_2",
                "city",
                "state_code",
                "country_code",
                "postal_code",
            ].forEach((field) => {
                data[type][field] = form.elements.namedItem(
                    `${type}[${field}]`,
                ).value;
            });
        });
        return data;
    }
    function queuePreview() {
        clearTimeout(previewTimer);
        previewController?.abort();
        const version = ++previewVersion;
        pricingFingerprint = null;
        form.querySelectorAll(
            "[data-total], [data-line-tax], [data-line-total]",
        ).forEach((node) => {
            node.textContent = "—";
        });
        form.querySelector("[data-preview-message]").textContent =
            "Calculating…";
        previewTimer = setTimeout(() => preview(version), 350);
    }
    async function preview(version) {
        previewController = new AbortController();
        try {
            const response = await send(
                config.previewUrl,
                previewPayload(),
                "POST",
                previewController.signal,
            );
            if (version !== previewVersion) {
                return;
            }
            pricingFingerprint = response.data.fingerprint;
            form.querySelectorAll("[data-total]").forEach((node) => {
                node.textContent = response.data.totals[node.dataset.total];
            });
            response.data.items.forEach((item, index) => {
                tbody.rows[index].querySelector("[data-line-tax]").textContent =
                    `${item.tax_amount} (${item.tax_rate}%)`;
                tbody.rows[index].querySelector(
                    "[data-line-total]",
                ).textContent = item.total_amount;
            });
            form.querySelector("[data-preview-message]").textContent =
                "Calculated from current catalog prices and selected taxes. Totals are checked again when saving.";
        } catch (error) {
            if (error.name === "AbortError" || version !== previewVersion) {
                return;
            }
            form.querySelector("[data-preview-message]").textContent =
                Object.values(error.errors || {})
                    .flat()
                    .join(" ") ||
                error.message ||
                "Unable to calculate totals.";
        }
    }
    form.querySelector("[data-add-item]").addEventListener("click", () => {
        addItem();
        queuePreview();
    });
    form.addEventListener("input", (event) => {
        if (
            event.target.closest("[data-order-items]") ||
            event.target.name === "shipping_amount"
        ) {
            queuePreview();
        }
    });
    $(form.elements.shipping_tax_id).on("change", queuePreview);
    (config.items.length ? config.items : [{}]).forEach(addItem);
    queuePreview();
    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (form.dataset.busy === "true") {
            return;
        }
        const valid = $(form).valid();
        if (tbody.rows.length === 0 || tbody.rows.length > 100) {
            showErrors(form, { message: "Add between 1 and 100 order items." });
            return;
        }
        if (!valid) {
            validator.focusInvalid();
            return;
        }
        const data = payload();
        data.submit_as = event.submitter?.value || "draft";
        if (data.submit_as === "confirmed") {
            if (!pricingFingerprint) {
                showErrors(form, {
                    message:
                        "Wait for a successful totals preview before confirming.",
                });
                return;
            }
            data.expected_pricing_fingerprint = pricingFingerprint;
            form.dataset.busy = "true";
            const result = await Swal.fire({
                title: "Confirm order?",
                text: "The order will be locked for editing and its stock reserved.",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Confirm order",
            });
            form.dataset.busy = "false";
            if (!result.isConfirmed) {
                return;
            }
        }
        save(form, data, form.elements._method?.value || "POST");
    });
});
