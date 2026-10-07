@props(['id' => 'listingFilters', 'filters' => []])
@php
    $activeCount = collect(request()->only($filters))
        ->filter(fn($value) => is_scalar($value) && trim((string) $value) !== '')
        ->count();
@endphp
<button type="button" {{ $attributes->class(['btn', 'btn-outline-primary']) }} data-bs-toggle="offcanvas"
    data-bs-target="#{{ $id }}" aria-controls="{{ $id }}">
    <i class="fa-solid fa-filter me-1" aria-hidden="true"></i>Filter
    @if ($activeCount > 0)
        <span class="badge text-bg-primary ms-1">{{ $activeCount }}<span class="visually-hidden"> active
                filters</span></span>
    @endif
</button>
