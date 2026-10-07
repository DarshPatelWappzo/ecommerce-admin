@props(['id' => 'listingFilters', 'action', 'filters' => [], 'showErrors' => true])
@if ($showErrors && $errors->any())
    <div class="alert alert-danger" role="alert">
        @foreach ($errors->all() as $message)
            <div>{{ $message }}</div>
        @endforeach
    </div>
@endif
@push('overlays')
    <div class="offcanvas offcanvas-end filter-offcanvas" tabindex="-1" id="{{ $id }}"
        aria-labelledby="{{ $id }}Label">
        <div class="offcanvas-header border-bottom">
            <h2 class="offcanvas-title h5" id="{{ $id }}Label">Filters</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close filters"></button>
        </div>
        <form method="GET" action="{{ $action }}" class="filter-offcanvas-form" novalidate>
            @foreach (request()->except([...$filters, 'page', '_token']) as $name => $value)
                @if (is_scalar($value) && trim((string) $value) !== '')
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <div class="offcanvas-body">
                {{ $slot }}
            </div>
            <div class="border-top p-3 d-flex justify-content-end gap-2 flex-shrink-0">
                <a class="btn btn-outline-secondary" href="{{ $action }}">Reset</a>
                <button class="btn btn-primary" type="submit">Apply Filter</button>
            </div>
        </form>
    </div>
@endpush
