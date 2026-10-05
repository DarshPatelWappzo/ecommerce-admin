(() => {
    const root = document.querySelector('[data-catalog-editor]');
    if (!root) return;
    const catalog = JSON.parse(root.dataset.catalog);
    root.querySelectorAll('[data-catalog-form]').forEach(form => {
        const type = form.dataset.catalogForm;
        const options = form.querySelector('[data-options]');
        const identifier = form.elements[type === 'tags' ? 'slug' : 'code'];
        let manualIdentifier = Boolean(form.elements.id.value || identifier.value);
        identifier.addEventListener('input', () => { manualIdentifier = true; });
        form.elements.name.addEventListener('input', event => {
            if (manualIdentifier) return;
            const slug = event.target.value.toLowerCase().normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            let value = type === 'tags' ? slug : slug.replaceAll('-', '_');
            if (type === 'attributes' && /^[0-9]/.test(value)) value = 'attribute_' + value;
            identifier.value = value.slice(0, 191).replace(/[-_]$/, '');
        });
        function syncAttributeFields() {
            if (!options) return;
            const isSelect = form.elements.type.value === 'select';
            form.querySelector('[data-option-fields]').hidden = !isSelect;
            if (!isSelect) form.elements.is_variant.checked = false;
        }
        form.elements.type?.addEventListener('change', syncAttributeFields);
        form.elements.is_variant?.addEventListener('change', () => {
            if (form.elements.is_variant.checked) form.elements.type.value = 'select';
            syncAttributeFields();
        });
        syncAttributeFields();
        function addOption(value = {}) {
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2';
            row.dataset.id = value.id || '';
            ['value', 'label', 'sort_order'].forEach(key => {
                const input = document.createElement('input');
                input.className = 'form-control col';
                input.dataset.key = key;
                input.type = key === 'sort_order' ? 'number' : 'text';
                input.value = value[key] ?? (key === 'sort_order' ? options.children.length : '');
                input.placeholder = key.replace('_', ' ');
                input.setAttribute('aria-label', input.placeholder);
                row.append(input);
            });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger col-auto';
            remove.textContent = 'Remove';
            remove.addEventListener('click', () => row.remove());
            row.append(remove);
            options.append(row);
        }
        form.querySelector('[data-add-option]')?.addEventListener('click', () => addOption());
        form.elements.id.addEventListener('change', () => {
            const record = catalog[type].find(row => String(row.id) === form.elements.id.value) || {};
            manualIdentifier = Boolean(record.id);
            ['name', 'slug', 'code', 'type'].forEach(key => {
                if (form.elements[key]) form.elements[key].value = record[key] || (key === 'type' ? 'select' : '');
            });
            ['status', 'is_variant', 'is_filterable'].forEach(key => {
                if (form.elements[key]) form.elements[key].checked = record[key] ?? key === 'status';
            });
            if (options) { options.replaceChildren(); (record.options || []).forEach(addOption); }
            syncAttributeFields();
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const error = form.querySelector('[data-form-error]');
            const success = form.querySelector('[data-success]');
            error.classList.add('d-none'); success.classList.add('d-none');
            const button = form.querySelector('[type="submit"]');
            button.disabled = true;
            const data = { name: form.elements.name.value, status: form.elements.status.checked };
            if (form.elements.id.value) data.id = Number(form.elements.id.value);
            if (type === 'tags') data.slug = form.elements.slug.value;
            else {
                ['code', 'type'].forEach(key => { data[key] = form.elements[key].value; });
                ['is_variant', 'is_filterable'].forEach(key => { data[key] = form.elements[key].checked; });
                data.options = data.type === 'select' ? [...options.children].map(row => {
                    const value = {};
                    if (row.dataset.id) value.id = Number(row.dataset.id);
                    row.querySelectorAll('[data-key]').forEach(input => { value[input.dataset.key] = input.value; });
                    return value;
                }) : [];
            }
            try {
                const response = await fetch(form.action, {
                    method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value },
                    body: JSON.stringify(data),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message);
                const index = catalog[type].findIndex(row => row.id === result.data.id);
                if (index < 0) { catalog[type].push(result.data); form.elements.id.add(new Option(result.data.name, result.data.id)); }
                else { catalog[type][index] = result.data; [...form.elements.id.options].find(o => o.value === String(result.data.id)).textContent = result.data.name; }
                form.elements.id.value = result.data.id;
                form.elements.id.dispatchEvent(new Event('change'));
                success.textContent = result.message; success.classList.remove('d-none');
            } catch (problem) {
                error.textContent = problem.message || 'Unable to save. Please try again.'; error.classList.remove('d-none');
            } finally { button.disabled = false; }
        });
    });
})();
