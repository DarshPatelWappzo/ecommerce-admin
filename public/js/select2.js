(() => {
    if (!window.jQuery?.fn?.select2) {
        return;
    }

    const initializeSelect2 = (root = document) => {
        const selects = root.matches?.('select') ? [root] : root.querySelectorAll?.('select');

        selects?.forEach((select) => {
            if (select.dataset.select2Disabled !== undefined || select.classList.contains('select2-hidden-accessible')) {
                return;
            }

            const placeholderOption = select.querySelector('option[value=""]');
            const placeholder = select.dataset.placeholder ?? placeholderOption?.textContent?.trim();

            window.jQuery(select).select2({
                placeholder,
                width: '100%',
                closeOnSelect: !select.multiple,
            }).on('select2:select select2:unselect select2:clear', () => {
                select.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    };

    initializeSelect2();

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) {
                    initializeSelect2(node);
                }
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
})();
