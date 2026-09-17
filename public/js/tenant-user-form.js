(() => {
    const form = document.querySelector('[data-tenant-user-form], [data-tenant-category-form]');

    if (!form) {
        return;
    }

    const formError = form.querySelector('[data-form-error]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        form.querySelectorAll('[data-error-for]').forEach((error) => {
            error.textContent = '';
        });
        formError.classList.add('d-none');
        formError.textContent = '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                },
            });
            const data = await response.json();

            if (response.status === 422) {
                Object.entries(data.errors ?? {}).forEach(([field, messages]) => {
                    const error = form.querySelector(`[data-error-for="${field}"]`)
                        ?? form.querySelector(`[data-error-for="${field.split('.')[0]}"]`);
                    if (error) {
                        error.textContent = messages[0];
                    }
                });
                return;
            }

            if (!response.ok) {
                throw new Error(form.dataset.saveError || 'Unable to save the user. Please try again.');
            }

            window.location.href = data.redirect;
        } catch (error) {
            formError.textContent = error.message;
            formError.classList.remove('d-none');
        }
    });

    form.querySelectorAll('input, select').forEach((field) => {
        const clearFieldError = () => {
            const errorField = field.name.replace(/\[\]$/, '');
            const error = form.querySelector(`[data-error-for="${errorField}"]`);
            const isValidValue = (field.value.trim() !== '' || field.name === 'parent_id')
                && (field.type !== 'email' || field.validity.valid);

            if (error && isValidValue) {
                error.textContent = '';
            }
        };

        field.addEventListener('input', clearFieldError);
        field.addEventListener('change', clearFieldError);
    });
})();
