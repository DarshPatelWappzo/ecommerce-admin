@props(['action', 'label' => 'Search', 'maxlength' => 200, 'count' => null])
<div class="listing-toolbar">
    <form method="GET" action="{{ $action }}" class="listing-search" role="search" novalidate>
        @foreach (request()->except(['search', 'page', '_token']) as $name => $value)
            @if (is_scalar($value) && trim((string) $value) !== '')
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach
        <div class="listing-search-field">
            <label class="visually-hidden" for="listing-search">{{ $label }}</label>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input class="form-control" id="listing-search" type="search" name="search"
                value="{{ is_scalar(request('search', '')) ? request('search', '') : '' }}"
                placeholder="{{ $label }}" maxlength="{{ $maxlength }}">
        </div>
        <button class="btn btn-outline-secondary" type="submit">Search</button>
    </form>
    @if ($count !== null)
        <span class="listing-result-count">{{ number_format($count) }} {{ $count === 1 ? 'result' : 'results' }}</span>
    @endif
</div>
