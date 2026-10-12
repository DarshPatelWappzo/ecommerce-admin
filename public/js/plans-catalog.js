(() => {
    const buttons = document.querySelectorAll("[data-billing-cycle]");
    buttons.forEach((button) => {
        button.addEventListener("click", () => {
            const cycle = button.dataset.billingCycle;
            buttons.forEach((option) => {
                const selected = option.dataset.billingCycle === cycle;
                option.classList.toggle("active", selected);
                option.setAttribute("aria-pressed", String(selected));
            });
            document.querySelectorAll("[data-cycle-price]").forEach((price) => {
                price.hidden = price.dataset.cyclePrice !== cycle;
            });
        });
    });
})();
