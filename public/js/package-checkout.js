(() => {
    const root = document.querySelector("[data-checkout-scope]");
    const urls = document.querySelector("[data-package-payment-urls]");
    if (!root || !urls) return;
    let busy = false;
    const buttons = () =>
        document.querySelectorAll(
            "[data-package-checkout], [data-package-resume]",
        );
    const lock = (value) => {
        busy = value;
        buttons().forEach((button) => {
            button.disabled = value;
        });
    };
    const message = (text, icon = "info") => Swal.fire({ text, icon });
    const request = async (url, body) => {
        const response = await fetch(url, {
            method: body ? "POST" : "GET",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN":
                    document.querySelector('meta[name="csrf-token"]')
                        ?.content || "",
            },
            ...(body ? { body: JSON.stringify(body) } : {}),
        });
        const data = await response.json();
        if (!response.ok)
            throw new Error(
                data.message ||
                    "Payment confirmation is unavailable. Retry from payment history.",
            );
        return data.data;
    };
    const path = (template, id) => template.replace("/0", "/" + id);
    const feedback = (payment) =>
        payment.message ||
        (payment.status === "captured"
            ? "Package activated"
            : "Payment is awaiting capture. Check payment history shortly.");
    const open = async (payment, storageKey) => {
        if (payment.status === "captured") {
            await message(
                feedback(payment),
                payment.fulfillment_status === "resolution_required"
                    ? "warning"
                    : "success",
            );
            location.reload();
            return;
        }
        if (!payment.checkout) {
            if (
                storageKey &&
                (payment.local_checkout_status === "expired" ||
                    payment.status === "rejected")
            )
                sessionStorage.removeItem(storageKey);
            await message(
                payment.message ||
                    "Payment pending. Check payment history or choose another package.",
            );
            location.reload();
            return;
        }
        if (typeof Razorpay !== "function")
            throw new Error(
                "Checkout could not load. Retry from payment history.",
            );
        lock(true);
        let verifying = false;
        const checkout = new Razorpay({
            ...payment.checkout,
            handler: async (result) => {
                verifying = true;
                try {
                    const verified = await request(
                        path(urls.dataset.verify, payment.id),
                        result,
                    );
                    if (verified.status === "captured" && storageKey)
                        sessionStorage.removeItem(storageKey);
                    await message(
                        feedback(verified),
                        verified.fulfillment_status === "resolution_required"
                            ? "warning"
                            : verified.status === "captured"
                              ? "success"
                              : "info",
                    );
                    location.reload();
                } catch (error) {
                    await message(error.message, "warning");
                } finally {
                    lock(false);
                }
            },
            modal: {
                ondismiss: async () => {
                    if (verifying) return;
                    try {
                        if (urls.dataset.dismiss)
                            await request(
                                path(urls.dataset.dismiss, payment.id),
                                {},
                            );
                    } catch {
                        // Local feedback does not depend on delivery of the dismissal notification.
                    }
                    if (storageKey) sessionStorage.removeItem(storageKey);
                    lock(false);
                    await message(
                        "Checkout dismissed; you can retry or choose another package. Any later capture will still be verified.",
                    );
                    location.reload();
                },
            },
        });
        checkout.on("payment.failed", () =>
            message(
                "Payment failed. You can retry or choose another package.",
                "warning",
            ),
        );
        checkout.open();
    };
    document.querySelectorAll("[data-billing-cycle]").forEach((button) =>
        button.addEventListener("click", () => {
            document.querySelectorAll("[data-plan-cycle]").forEach((action) => {
                action.hidden =
                    action.dataset.planCycle !== button.dataset.billingCycle;
            });
        }),
    );
    buttons().forEach((button) =>
        button.addEventListener("click", async () => {
            if (busy) return;
            lock(true);
            let storageKey;
            try {
                let payment;
                if (button.dataset.packageResume) {
                    payment = await request(button.dataset.packageResume);
                } else {
                    storageKey = [
                        "platform-checkout",
                        root.dataset.checkoutScope,
                        root.dataset.checkoutMode,
                        button.dataset.package,
                        button.dataset.cycle,
                        button.dataset.quote,
                    ].join(":");
                    let key = sessionStorage.getItem(storageKey);
                    if (!key) {
                        key = crypto.randomUUID();
                        sessionStorage.setItem(storageKey, key);
                    }
                    payment = await request(button.dataset.packageCheckout, {
                        billing_cycle: button.dataset.cycle,
                        idempotency_key: key,
                    });
                    if (["captured", "rejected"].includes(payment.status))
                        sessionStorage.removeItem(storageKey);
                }
                lock(false);
                await open(payment, storageKey);
            } catch (error) {
                lock(false);
                await message(error.message, "warning");
            }
        }),
    );
})();
