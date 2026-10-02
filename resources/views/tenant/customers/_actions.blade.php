@if ($canUpdate)
    <a class="btn btn-sm btn-outline-primary" href="{{ route('tenant.customers.edit', $customer) }}">Edit</a>
    <form method="POST" action="{{ route('tenant.customers.status', $customer) }}">@csrf @method('PATCH')
        <input type="hidden" name="status" value="{{ $customer->status === 'active' ? 'inactive' : 'active' }}">
        <button
            class="btn btn-sm btn-outline-secondary">{{ $customer->status === 'active' ? 'Deactivate' : 'Activate' }}</button>
    </form>
@endif
@if ($canDelete)
    <form method="POST" action="{{ route('tenant.customers.destroy', $customer) }}"
        data-confirm-delete="Delete this customer?">@csrf @method('DELETE')<button
            class="btn btn-sm btn-outline-danger">Delete</button></form>
@endif
