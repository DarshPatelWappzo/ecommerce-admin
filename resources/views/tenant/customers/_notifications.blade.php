@foreach (['success' => 'success', 'error' => 'danger'] as $key => $style)
    @if (session($key))
        <div class="alert alert-{{ $style }}" role="alert">{{ session($key) }}</div>
    @endif
@endforeach
@include('tenant.partials.validation-errors')
