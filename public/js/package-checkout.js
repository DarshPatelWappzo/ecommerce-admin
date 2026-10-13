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
    const open = async (payment) => {
        if (payment.status === "captured") {
            await message(
                "Payment confirmed. Your subscription has been updated.",
                "success",
            );
            location.reload();
            return;
        }
        if (!payment.checkout) {
            await message(
                payment.status === "rejected"
                    ? "Checkout was rejected. Contact support, then start a new purchase."
                    : "Order confirmation is pending. Use Check / resume in payment history; another order will not be created.",
            );
            location.reload();
            return;
        }
        if (typeof Razorpay !== "function")
            throw new Error(
                "Checkout could not load. Retry from payment history.",
            );
        lock(true);
        const checkout = new Razorpay({
            ...payment.checkout,
            handler: async (result) => {
                try {
                    const verified = await request(
                        path(urls.dataset.verify, payment.id),
                        result,
                    );
                    if (verified.status === "captured")
                        sessionStorage.removeItem(
                            "platform-checkout:" +
                                root.dataset.checkoutScope +
                                ":" +
                                payment.package_id +
                                ":" +
                                payment.billing_cycle,
                        );
                    await message(
                        verified.status === "captured"
                            ? "Payment confirmed. Subscription updated."
                            : "Payment is awaiting capture. Check payment history shortly.",
                        verified.status === "captured" ? "success" : "info",
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
                    lock(false);
                    await message(
                        "Checkout dismissed. You can resume it from payment history. Any later capture will still be confirmed.",
                    );
                    location.reload();
                },
            },
        });
        checkout.on("payment.failed", () =>
            message(
                "Payment failed. Retry within checkout or resume from payment history.",
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
            try {
                let payment;
                if (button.dataset.packageResume) {
                    payment = await request(button.dataset.packageResume);
                } else {
                    const storageKey =
                        "platform-checkout:" +
                        root.dataset.checkoutScope +
                        ":" +
                        button.dataset.package +
                        ":" +
                        button.dataset.cycle;
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
                await open(payment);
            } catch (error) {
                lock(false);
                await message(error.message, "warning");
            }
        }),
    );
})();
