(() => {
    async function submit(url, body) {
        const response = await fetch(url, {
            method: "POST",
            body,
            headers: { Accept: "application/json" },
        });
        let data;
        try {
            data = await response.json();
        } catch {
            throw new Error(
                "Unable to save. Please sign in again or try later.",
            );
        }
        if (!response.ok) {
            const error = new Error(data.message || "Unable to save.");
            error.fields = data.errors || {};
            throw error;
        }
        return data;
    }

    document.querySelectorAll("[data-product-delete]").forEach((form) => {
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const result = await Swal.fire({
                icon: "warning",
                title: "Delete this product?",
                text: "This will delete the product and its sellable variants.",
                showCancelButton: true,
                confirmButtonText: "Yes, delete it",
                cancelButtonText: "Cancel",
                confirmButtonColor: "#dc3545",
                focusCancel: true,
            });
            if (!result.isConfirmed) return;
            const button = form.querySelector("button");
            button.disabled = true;
            try {
                const data = await submit(form.action, new FormData(form));
                window.location.href = data.redirect;
            } catch (error) {
                const box = document.querySelector("[data-list-error]");
                box.textContent =
                    Object.values(error.fields || {})
                        .flat()
                        .join(" ") || error.message;
                box.classList.remove("d-none");
                button.disabled = false;
            }
        });
    });

    const form = document.querySelector("[data-product-form]");
    if (!form) return;
    const product = JSON.parse(form.dataset.product) || {};
    const catalog = JSON.parse(form.dataset.catalog);
    const variants = form.querySelector("[data-variants]");
    const attributes = form.querySelector("[data-attributes]");
    const images = form.querySelector("[data-images]");
    let sequence = 0;

    function field(
        container,
        key,
        label,
        value = "",
        type = "text",
        choices = null,
    ) {
        const wrapper = document.createElement("div");
        wrapper.className = "col-md-4";
        const labelNode = document.createElement("label");
        labelNode.className = "form-label";
        labelNode.textContent = label;
        const input = document.createElement(choices ? "select" : "input");
        input.className = choices ? "form-select" : "form-control";
        input.id = "product-field-" + ++sequence;
        input.dataset.field = key;
        labelNode.htmlFor = input.id;
        if (choices) {
            input.dataset.select2Disabled = "";
            choices.forEach(([id, text]) => {
                const option = new Option(text, id);
                option.selected = String(id) === String(value ?? "");
                input.add(option);
            });
        } else {
            input.type = type;
            if (type !== "file") input.value = value ?? "";
            if (type === "number") {
                input.min = "0";
                input.step = "any";
            }
            if (type === "file")
                input.accept = "image/jpeg,image/png,image/webp";
        }
        const error = document.createElement("span");
        error.className = "field-error";
        error.dataset.key = key;
        wrapper.append(labelNode, input, error);
        container.append(wrapper);
        return input;
    }

    function card(container, title, row = {}) {
        const card = document.createElement("div");
        card.className = "border rounded p-3 mt-3";
        card.dataset.row = "";
        card.dataset.id = row.id || "";
        const header = document.createElement("div");
        header.className = "d-flex justify-content-between mb-3";
        const heading = document.createElement("strong");
        heading.textContent = title;
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "btn btn-sm btn-outline-danger";
        remove.textContent = "Remove";
        remove.addEventListener("click", () => {
            card.remove();
            refreshImageVariants();
        });
        header.append(heading, remove);
        const grid = document.createElement("div");
        grid.className = "row g-3";
        card.append(header, grid);
        container.append(card);
        return [card, grid];
    }

    function addOption(container, value = {}, productLevel = false) {
        const row = document.createElement("div");
        row.className = "row g-3 border-top pt-3 mt-2";
        row.dataset.optionRow = "";
        const definitions = catalog.attributes.filter(
            (a) => productLevel || (a.is_variant && a.type === "select"),
        );
        const select = field(
            row,
            "attribute_id",
            "Attribute",
            value.attribute_id,
            "text",
            [
                ["", "Choose attribute"],
                ...definitions.map((a) => [a.id, a.name]),
            ],
        );
        const holder = document.createElement("div");
        holder.className = "col-md-6 row g-3";
        row.append(holder);
        const renderValue = () => {
            holder.replaceChildren();
            const definition = definitions.find(
                (a) => String(a.id) === select.value,
            );
            if (!definition) return;
            if (definition.type === "select") {
                field(
                    holder,
                    "attribute_option_id",
                    "Option",
                    value.attribute_option_id,
                    "text",
                    [
                        ["", "Choose option"],
                        ...definition.options.map((o) => [o.id, o.label]),
                    ],
                );
            } else if (definition.type === "boolean") {
                field(holder, "text_value", "Value", value.text_value, "text", [
                    ["", "Choose value"],
                    ["1", "Yes"],
                    ["0", "No"],
                ]);
            } else {
                field(
                    holder,
                    "text_value",
                    "Value",
                    value.text_value,
                    definition.type === "date"
                        ? "date"
                        : definition.type === "number"
                          ? "number"
                          : "text",
                );
            }
        };
        select.addEventListener("change", () => {
            value = {};
            renderValue();
        });
        renderValue();
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className =
            "btn btn-sm btn-outline-danger col-auto align-self-end";
        remove.textContent = "Remove attribute";
        remove.addEventListener("click", () => row.remove());
        row.append(remove);
        container.append(row);
    }

    function addVariant(value = {}) {
        const [row, grid] = card(variants, "Variant", value);
        row.dataset.variantKey = String(++sequence);
        field(grid, "sku", "SKU", value.sku).addEventListener(
            "input",
            refreshImageVariants,
        );
        field(grid, "barcode", "Barcode", value.barcode);
        [
            "price",
            "special_price",
            "cost_price",
            "weight",
            "quantity",
            "reserved_quantity",
            "reorder_level",
        ].forEach((key) => {
            const input = field(
                grid,
                key,
                key.replaceAll("_", " ").replace(/^./, (c) => c.toUpperCase()),
                value[key] ??
                    ([
                        "quantity",
                        "reserved_quantity",
                        "reorder_level",
                    ].includes(key)
                        ? 0
                        : ""),
                "number",
            );
            input.step = [
                "quantity",
                "reserved_quantity",
                "reorder_level",
            ].includes(key)
                ? "1"
                : key === "weight"
                  ? "0.001"
                  : "0.01";
        });
        field(
            grid,
            "special_price_from",
            "Special price starts",
            value.special_price_from?.slice(0, 10),
            "date",
        );
        field(
            grid,
            "special_price_to",
            "Special price ends",
            value.special_price_to?.slice(0, 10),
            "date",
        );
        field(grid, "status", "Status", Number(value.status ?? true), "text", [
            [1, "Active"],
            [0, "Inactive"],
        ]);
        const options = document.createElement("div");
        options.dataset.variantOptions = "";
        row.append(options);
        (value.attribute_values || []).forEach((option) =>
            addOption(options, option),
        );
        const add = document.createElement("button");
        add.className = "btn btn-sm btn-outline-secondary mt-3";
        add.type = "button";
        add.textContent = "Add variant attribute";
        add.addEventListener("click", () => addOption(options));
        row.append(add);
        refreshImageVariants();
    }

    function refreshImageVariants() {
        images
            .querySelectorAll('[data-field="variant_index"]')
            .forEach((select) => {
                const selected = select.value;
                select.replaceChildren(new Option("Whole product", ""));
                [...variants.children].forEach((row, index) => {
                    const label =
                        row.querySelector('[data-field="sku"]').value ||
                        "Variant " + (index + 1);
                    select.add(new Option(label, row.dataset.variantKey));
                });
                select.value = [...select.options].some(
                    (o) => o.value === selected,
                )
                    ? selected
                    : "";
            });
    }

    function addImage(value = {}) {
        const [row, grid] = card(images, "Image", value);
        if (value.url) {
            const preview = document.createElement("img");
            preview.src = value.url;
            preview.alt = value.alt_text || "Product image";
            preview.width = 100;
            preview.className = "rounded mb-2";
            row.prepend(preview);
        }
        field(
            grid,
            "file",
            value.id ? "Replace image (optional)" : "Image file",
            "",
            "file",
        );
        field(grid, "alt_text", "Alt text", value.alt_text);
        field(
            grid,
            "sort_order",
            "Sort order",
            value.sort_order ?? images.children.length - 1,
            "number",
        );
        const primary = field(
            grid,
            "is_primary",
            "Primary image",
            Number(value.is_primary ?? false),
            "text",
            [
                [0, "No"],
                [1, "Yes"],
            ],
        );
        primary.addEventListener("change", () => {
            if (primary.value === "1")
                images
                    .querySelectorAll('[data-field="is_primary"]')
                    .forEach((other) => {
                        if (other !== primary) other.value = "0";
                    });
        });
        const variantSelect = field(
            grid,
            "variant_index",
            "Variant image",
            "",
            "text",
            [["", "Whole product"]],
        );
        refreshImageVariants();
        const index = (product.variants || []).findIndex(
            (v) => v.id === value.variant_id,
        );
        if (index >= 0)
            variantSelect.value = variants.children[index].dataset.variantKey;
    }

    function values(row, prefix, excludeOptions = false) {
        const result = {};
        row.querySelectorAll("[data-field]").forEach((input) => {
            if (excludeOptions && input.closest("[data-option-row]")) return;
            if (input.type !== "file")
                result[input.dataset.field] =
                    input.value === "" ? null : input.value;
            const error = input.parentElement.querySelector("[data-key]");
            if (error)
                error.dataset.errorFor = prefix + "." + input.dataset.field;
        });
        return result;
    }

    form.querySelector("[data-add-variant]").addEventListener("click", () =>
        addVariant(),
    );
    form.querySelector("[data-add-attribute]").addEventListener("click", () =>
        addOption(attributes, {}, true),
    );
    form.querySelector("[data-add-image]").addEventListener("click", () =>
        addImage(),
    );
    (product.variants?.length ? product.variants : [{}]).forEach(addVariant);
    (product.attribute_values || []).forEach((value) =>
        addOption(attributes, value, true),
    );
    (product.images || []).forEach(addImage);
    const slug = form.querySelector('[name="slug"]');
    let manualSlug = Boolean(product.id);
    slug.addEventListener("input", () => {
        manualSlug = true;
    });
    form.querySelector('[name="name"]').addEventListener("input", (event) => {
        if (!manualSlug)
            slug.value = event.target.value
                .toLowerCase()
                .normalize("NFKD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/^-|-$/g, "");
    });

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        form.querySelectorAll(".field-error").forEach((error) => {
            error.textContent = "";
        });
        if (
            window.jQuery &&
            jQuery(form).data("validator") &&
            !jQuery(form).valid()
        )
            return;
        const errorBox = form.querySelector("[data-form-error]");
        errorBox.classList.add("d-none");
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        const payload = {};
        [
            "name",
            "slug",
            "product_type",
            "short_description",
            "description",
            "status",
            "meta_title",
            "meta_description",
        ].forEach((key) => {
            payload[key] = form.elements[key].value;
        });
        payload.featured = form.elements.featured.checked;
        payload.hsn_code = form.elements.hsn_code.value || null;
        payload.tax_id =
            form.elements.tax_id.value === ""
                ? null
                : form.elements.tax_id.value;
        ["category_ids", "tag_ids", "related_product_ids"].forEach((key) => {
            payload[key] = [
                ...form.querySelector("#" + key).selectedOptions,
            ].map((option) => Number(option.value));
        });
        payload.variants = [...variants.children].map((row, index) => {
            const value = values(row, "variants." + index, true);
            if (row.dataset.id) value.id = Number(row.dataset.id);
            value.options = [...row.querySelectorAll("[data-option-row]")].map(
                (option, i) =>
                    values(option, "variants." + index + ".options." + i),
            );
            return value;
        });
        payload.attributes = [...attributes.children].map((row, i) =>
            values(row, "attributes." + i),
        );
        payload.images = [...images.children].map((row, index) => {
            const value = values(row, "images." + index);
            if (row.dataset.id) value.id = Number(row.dataset.id);
            if (value.variant_index !== null) {
                const variantIndex = [...variants.children].findIndex(
                    (variant) =>
                        variant.dataset.variantKey === value.variant_index,
                );
                value.variant_index = variantIndex >= 0 ? variantIndex : null;
            }
            return value;
        });
        const body = new FormData();
        body.append("_token", form.elements._token.value);
        if (form.elements._method)
            body.append("_method", form.elements._method.value);
        body.append("payload", JSON.stringify(payload));
        [...images.children].forEach((row, index) => {
            const file = row.querySelector('[type="file"]').files[0];
            if (file) body.append("images[" + index + "][file]", file);
        });
        try {
            const data = await submit(form.action, body);
            window.location.href = data.redirect;
        } catch (error) {
            if (window.jQuery && jQuery(form).data("validator")) {
                const inlineErrors = {};
                ["tax_id", "hsn_code"].forEach((field) => {
                    if (error.fields?.[field])
                        inlineErrors[field] = error.fields[field][0];
                });
                jQuery(form).validate().showErrors(inlineErrors);
            }
            Object.entries(error.fields || {}).forEach(([key, messages]) => {
                const target = [
                    ...form.querySelectorAll("[data-error-for]"),
                ].find((el) => el.dataset.errorFor === key);
                if (target) target.textContent = messages[0];
            });
            errorBox.textContent =
                Object.values(error.fields || {})
                    .flat()
                    .join(" ") || error.message;
            errorBox.classList.remove("d-none");
            errorBox.scrollIntoView({ behavior: "smooth", block: "center" });
            button.disabled = false;
        }
    });
})();
