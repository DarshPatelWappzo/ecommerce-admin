(() => {
    const pending = new WeakSet();
    const approved = new WeakSet();

    document.addEventListener("submit", async (event) => {
        const form = event.target;
        if (form.querySelector('[name="_method"]')?.value.toUpperCase() !== "DELETE") return;
        if (approved.has(form)) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        if (pending.has(form)) return;
        pending.add(form);

        try {
            const result = await Swal.fire({
                icon: "warning",
                title: form.dataset.confirmDelete || "Delete this item?",
                text: form.dataset.deleteMessage || "Are you sure you want to delete this item?",
                showCancelButton: true,
                confirmButtonText: "Yes, delete it",
                cancelButtonText: "Cancel",
                confirmButtonColor: "#dc3545",
                focusCancel: true,
            });
            if (result.isConfirmed) {
                approved.add(form);
                try {
                    form.requestSubmit(event.submitter || undefined);
                } finally {
                    approved.delete(form);
                }
            }
        } finally {
            pending.delete(form);
        }
    }, true);
})();
