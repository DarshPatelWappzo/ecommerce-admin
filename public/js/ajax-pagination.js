(() => {
    let activeRequest;
    let searchTimer;

    const loadAsyncPage = async (url, container, historyMode = 'push', keepSearchFocus = false) => {
        activeRequest?.abort();
        activeRequest = new AbortController();
        container.classList.add('pagination-loading');
        container.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                signal: activeRequest.signal,
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Unable to load this page.');
            }

            const html = await response.text();
            const documentParser = new DOMParser();
            const page = documentParser.parseFromString(html, 'text/html');
            const updatedContainer = page.querySelector('[data-ajax-pagination-container]');

            if (!updatedContainer) {
                throw new Error('Unable to load this page.');
            }

            container.replaceWith(updatedContainer);

            if (historyMode === 'push') {
                window.history.pushState({}, '', url);
            } else if (historyMode === 'replace') {
                window.history.replaceState({}, '', url);
            }

            if (keepSearchFocus) {
                const searchInput = updatedContainer.querySelector('[data-ajax-search]');
                searchInput?.focus();
                searchInput?.setSelectionRange(searchInput.value.length, searchInput.value.length);
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            container.classList.remove('pagination-loading');
            container.removeAttribute('aria-busy');
            window.alert(error.message);
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-ajax-pagination-container] .pagination a');

        if (!link) {
            return;
        }

        event.preventDefault();
        loadAsyncPage(link.href, link.closest('[data-ajax-pagination-container]'));
    });

    document.addEventListener('input', (event) => {
        const searchInput = event.target.closest('[data-ajax-search]');

        if (!searchInput) {
            return;
        }

        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            const url = new URL(window.location.href);
            const search = searchInput.value.trim();

            url.searchParams.delete('page');

            if (search === '') {
                url.searchParams.delete('search');
            } else {
                url.searchParams.set('search', search);
            }

            loadAsyncPage(
                url.toString(),
                searchInput.closest('[data-ajax-pagination-container]'),
                'replace',
                true,
            );
        }, 300);
    });

    window.addEventListener('popstate', () => {
        const container = document.querySelector('[data-ajax-pagination-container]');

        if (container) {
            loadAsyncPage(window.location.href, container, 'none');
        }
    });
})();
