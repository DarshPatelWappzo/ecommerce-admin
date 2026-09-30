(() => {
    "use strict";

    const button = document.getElementById("order-razorpay-pay");
    if (!button) return;

    const feedback = document.getElementById("order-payment-feedback");
    const refresh = document.getElementById("order-payment-refresh");
    let verifying = false;
    let awaitingConfirmation = false;

    function message(text, type = "info") {
        feedback.textContent = text;
        feedback.className = `alert alert-${type}`;
        refresh.classList.remove("d-none");
    }

    async function request(url, body) {
        const response = await fetch(url, {
            method: body === undefined ? "GET" : "POST",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,
            },
            ...(body === undefined ? {} : { body: JSON.stringify(body) }),
        });
        const data = await response.json().catch(() => ({}));
        console.log("Payment request response", { url, body, response, data });
        if (!response.ok) {
            const errors = Object.values(data.errors || {}).flat();
            throw new Error(
                response.status === 422
                    ? errors[0] || "Payment details are invalid."
                    : response.status === 401 || response.status === 419
                      ? "Your session expired. Reload the page and sign in again."
                      : response.status === 403
                        ? "You do not have permission to accept payments."
                        : "Payment could not be confirmed. Check its status before trying again.",
            );
        }
        return data.data;
    }

    async function checkStatus() {
        const order = await request(button.dataset.statusUrl);
        if (order.payment_status === "paid") {
            button.disabled = true;
            message("Payment confirmed. Refreshing order…", "success");
            window.location.reload();
            return true;
        }
        message(
            "Payment is not confirmed as paid yet. If money was deducted, wait for confirmation before retrying.",
            "warning",
        );
        return false;
    }

    refresh.addEventListener("click", async () => {
        refresh.disabled = true;
        try {
            await checkStatus();
        } catch (error) {
            message(error.message, "warning");
        } finally {
            refresh.disabled = false;
        }
    });

    button.addEventListener("click", async () => {
        if (button.disabled) return;
        button.disabled = true;
        try {
            if (typeof window.Razorpay !== "function") {
                throw new Error(
                    "Razorpay Checkout could not load. Reload the page and try again.",
                );
            }
            message("Preparing secure checkout…");
            const checkout = await request(button.dataset.initiateUrl, {});
            const razorpay = new window.Razorpay({
                key: checkout.key,
                order_id: checkout.order_id,
                amount: checkout.amount,
                currency: checkout.currency,
                description: `Order ${button.dataset.orderNumber}`,
                handler: async (result) => {
                    verifying = true;
                    awaitingConfirmation = true;
                    button.disabled = true;
                    message("Verifying payment. Please wait…");
                    try {
                        await request(button.dataset.verifyUrl, {
                            razorpay_order_id: result.razorpay_order_id,
                            razorpay_payment_id: result.razorpay_payment_id,
                            razorpay_signature: result.razorpay_signature,
                        });
                        await checkStatus();
                    } catch (error) {
                        message(
                            `${error.message} Use Check payment status to confirm before making another payment.`,
                            "warning",
                        );
                    } finally {
                        verifying = false;
                    }
                },
                modal: {
                    ondismiss: () => {
                        if (verifying || awaitingConfirmation) return;
                        button.disabled = false;
                        message(
                            "Checkout closed. Payment has not been confirmed. Check its status if money was deducted.",
                            "warning",
                        );
                    },
                },
            });
            razorpay.on("payment.failed", () => {
                message(
                    "Payment attempt failed. You can retry within Checkout. If money was deducted, check payment status first.",
                    "warning",
                );
            });
            razorpay.open();
        } catch (error) {
            button.disabled = false;
            message(error.message, "warning");
        }
    });
})();
