jQuery(function ($) {
    const confirming = new WeakSet();

    $("[data-replacement-confirm]").each(function () {
        const validator = $(this).data("validator") || $(this).validate();
        validator.settings.normalizer = function (value) {
            return typeof value === "string" ? value.trim() : value;
        };
        validator.settings.submitHandler = function (form) {
            if (confirming.has(form)) return false;
            confirming.add(form);

            Swal.fire({
                icon: "warning",
                title: form.dataset.replacementConfirm,
                showCancelButton: true,
                confirmButtonText: "Confirm",
                cancelButtonText: "Cancel",
                focusCancel: true,
            })
                .then(function (result) {
                    if (result.isConfirmed) {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                })
                .finally(function () {
                    confirming.delete(form);
                });

            return false;
        };
    });
});
