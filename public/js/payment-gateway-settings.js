document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("payment-gateway-form");
    const button = document.getElementById("test-gateway");
    const enabled = document.getElementById("is_enabled");
    let testing = false;
    function updateConfiguration() {
        form.querySelectorAll("[data-gateway-configuration]").forEach(
            function (field) {
                field.disabled = !enabled.checked;
            },
        );
        if (button) {
            button.disabled = !enabled.checked || testing;
        }
    }
    enabled.addEventListener("change", updateConfiguration);
    updateConfiguration();
    button?.addEventListener("click", function () {
        testing = true;
        button.disabled = true;
        $.ajax({
            url: button.dataset.url,
            method: "POST",
            data: {
                _token: form.querySelector('[name="_token"]').value,
                mode: form.querySelector('select[name="mode"]').value,
            },
            headers: { Accept: "application/json" },
        })
            .done(function (response) {
                Swal.fire({
                    icon: "success",
                    text: response.message,
                });
            })
            .fail(function (xhr) {
                Swal.fire({
                    icon: "error",
                    text:
                        xhr.responseJSON?.message ||
                        "Connection test could not be completed.",
                });
            })
            .always(function () {
                testing = false;
                updateConfiguration();
            });
    });
    document.querySelectorAll("[data-copy-webhook]").forEach(function (copy) {
        copy.addEventListener("click", async function () {
            const input = document.getElementById(copy.dataset.copyWebhook);
            try {
                await navigator.clipboard.writeText(input.value);
                Swal.fire({
                    icon: "success",
                    text: "Webhook URL copied.",
                });
            } catch {
                input.focus();
                input.select();
                Swal.fire({
                    icon: "info",
                    text: "Select and copy the webhook URL from the field.",
                });
            }
        });
    });
});
