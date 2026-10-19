@if ($canUpdate)
    <a class="btn btn-sm btn-outline-primary" href="{{ route('tenant.customers.edit', $customer) }}"
        aria-label="Edit customer" title="Edit">
        <i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>
    </a>
    <form method="POST" action="{{ route('tenant.customers.status', $customer) }}">@csrf @method('PATCH')
        <input type="hidden" name="status" value="{{ $customer->status === 'active' ? 'inactive' : 'active' }}">
        <button type="submit"
            class="btn btn-sm {{ $customer->status === 'active' ? 'btn-outline-warning' : 'btn-outline-success' }}"
            aria-label="{{ $customer->status === 'active' ? 'Deactivate customer' : 'Activate customer' }}"
            title="{{ $customer->status === 'active' ? 'Deactivate' : 'Activate' }}">
            <i class="fa-solid {{ $customer->status === 'active' ? 'fa-toggle-on' : 'fa-toggle-off' }} me-1"
                aria-hidden="true"></i>
        </button>
    </form>
@endif
@if ($canDelete)
    <form method="POST" action="{{ route('tenant.customers.destroy', $customer) }}"
        data-confirm-delete="Delete this customer?">@csrf @method('DELETE')<button
            class="btn btn-sm btn-outline-danger" type="submit" aria-label="Delete customer" title="Delete">
            <i class="fa-solid fa-trash me-1" aria-hidden="true"></i>
        </button></form>
@endif
