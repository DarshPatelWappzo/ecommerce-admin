@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])

@section('title', 'Edit Tax')

@section('content')
    <div class="container-fluid">
        <div class="mb-4">
            <p class="text-primary fw-semibold mb-1">Taxes</p>
            <h1 class="page-title mb-1">Edit Tax</h1>
        </div>
        @include('tenant.taxes._form')
    </div>
@endsection
