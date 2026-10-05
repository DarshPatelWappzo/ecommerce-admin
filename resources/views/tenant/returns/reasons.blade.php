@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Return reasons')
@section('content')
    <div class="container-fluid">
        <h1 class="page-title mb-4">Return reasons</h1>
        @include('tenant.customers._notifications')
        @foreach ($reasons as $reason)
            <form id="reason-{{ $reason->id }}" class="dashboard-card p-3 mb-3 row g-2" method="POST"
                action="{{ route('tenant.returns.reasons.save') }}">
                @csrf
                <input type="hidden" name="id" value="{{ $reason->id }}">
                <div class="col-md-6">
                    <label class="form-label">Name
                        <input class="form-control" name="name" value="{{ $reason->name }}" required maxlength="150">
                    </label>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status
                        <select class="form-select" name="status">
                            <option value="1" @selected($reason->status)>Active</option>
                            <option value="0" @selected(!$reason->status)>Inactive</option>
                        </select>
                    </label>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort order
                        <input class="form-control" name="sort_order" type="number" min="0" max="65535"
                            value="{{ $reason->sort_order }}" required>
                    </label>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary mb-2">Save</button>
                </div>
            </form>
        @endforeach
        <form id="reason-new" class="dashboard-card p-3" method="POST" action="{{ route('tenant.returns.reasons.save') }}">
            @csrf
            <h2 class="h5">Add reason</h2>
            <label class="form-label">Name<input class="form-control" name="name" required maxlength="150"></label>
            <input type="hidden" name="status" value="1"><input type="hidden" name="sort_order" value="0">
            <button class="btn btn-primary">Add</button>
        </form>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    @foreach ($reasons as $reason)
        {!! JsValidator::make(
            \App\Http\Requests\TenantReturnActionRequest::rulesFor('saveReason'),
            [],
            [],
            '#reason-' . $reason->id,
        ) !!}
    @endforeach
    {!! JsValidator::make(
        \App\Http\Requests\TenantReturnActionRequest::rulesFor('saveReason'),
        [],
        [],
        '#reason-new',
    ) !!}
@endpush
