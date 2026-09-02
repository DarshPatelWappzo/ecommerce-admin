(() => {
    const refreshDomainRows = (fields) => {
        fields.querySelectorAll('[data-domain-row]').forEach((row, index) => {
            const input = row.querySelector('[data-domain-input]');
            const error = row.querySelector('[data-error-for]');

            input.id = `domain-${index}`;
            error.dataset.errorFor = `domains.${index}`;
        });
    };

    document.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-add-domain]');
        const removeButton = event.target.closest('[data-remove-domain]');

        if (addButton) {
            const fields = addButton.closest('form').querySelector('[data-domain-fields]');
            const index = fields.querySelectorAll('[data-domain-row]').length;
            const row = document.createElement('div');

            row.className = 'mb-2';
            row.dataset.domainRow = '';
            row.innerHTML = `
                <div class="input-group">
                    <input class="form-control" id="domain-${index}" name="domains[]" type="text" placeholder="example.com" data-domain-input>
                    <button class="btn btn-outline-danger" type="button" data-remove-domain>
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                        <span class="visually-hidden">Remove domain</span>
                    </button>
                </div>
                <small class="field-error" data-error-for="domains.${index}"></small>
            `;

            fields.append(row);
            row.querySelector('[data-domain-input]').focus();

            return;
        }

        if (!removeButton) {
            return;
        }

        const fields = removeButton.closest('form').querySelector('[data-domain-fields]');
        const rows = fields.querySelectorAll('[data-domain-row]');

        if (rows.length === 1) {
            Swal.fire({
                icon: 'info',
                title: 'At least one domain is required',
                text: 'Add another domain before removing this one.',
                confirmButtonColor: '#4f46e5',
            });

            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Remove this domain?',
            text: 'It will be removed when you save the user.',
            showCancelButton: true,
            confirmButtonText: 'Yes, remove it',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545',
        }).then((result) => {
            if (result.isConfirmed) {
                removeButton.closest('[data-domain-row]').remove();
                refreshDomainRows(fields);
            }
        });
    });

    document.addEventListener('input', (event) => {
        const input = event.target.closest('[data-domain-input]');

        if (!input) {
            return;
        }

        const fields = input.closest('[data-domain-fields]');
        const index = [...fields.querySelectorAll('[data-domain-input]')].indexOf(input);
        const error = fields.querySelector(`[data-error-for="domains.${index}"]`);

        if (error && input.value.trim() !== '') {
            error.textContent = '';
        }
    });
})();
