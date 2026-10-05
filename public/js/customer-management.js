jQuery(function ($) {
    function normalizeContact(name, value) {
        if (typeof value !== "string") return value;
        value = value.trim();
        if (name === "gstin") value = value.toUpperCase();
        if (name === "email") value = value.toLowerCase();
        if (name === "phone") value = value.replace(/[\s().-]+/g, "");
        if (name === "phone_country_code" && /^\+?[1-9][0-9]{0,3}$/.test(value))
            value = "+" + value.replace(/^\+/, "");
        return value;
    }
    $("#customer-form, #customer-address-form").each(function () {
        const validator = $(this).data("validator");
        if (validator)
            validator.settings.normalizer = function (value) {
                return normalizeContact(this.name, value);
            };
    });
    const form = $("#customer-form");
    function toggleBusiness() {
        const business =
            form.find('[name="customer_type"]').val() === "business";
        form.find("[data-business-field]").toggleClass("d-none", !business);
        if (form.data("validator"))
            form.find('[name="company_name"]').rules("add", {
                required: business,
            });
    }
    if (form.length) {
        toggleBusiness();
        form.find('[name="customer_type"]').on("change", toggleBusiness);
    }
    $("#customer-form, #customer-address-form")
        .find(
            '[name="gstin"], [name="email"], [name="phone"], [name="phone_country_code"]',
        )
        .on("change", function () {
            this.value = normalizeContact(this.name, this.value);
        });
    document.addEventListener("submit", async (event) => {
        const form = event.target.closest("[data-confirm-delete]");
        if (!form) return;
        event.preventDefault();
        const result = await Swal.fire({
            icon: "warning",
            title: form.dataset.confirmDelete,
            showCancelButton: true,
            confirmButtonText: "Delete",
            confirmButtonColor: "#dc3545",
            focusCancel: true,
        });
        if (result.isConfirmed) HTMLFormElement.prototype.submit.call(form);
    });
});
