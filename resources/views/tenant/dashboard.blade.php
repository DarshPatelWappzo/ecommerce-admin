@extends('layouts.app', [
    'sidebarView' => 'tenant.partials.sidebar',
    'headerView' => 'tenant.partials.header',
])

@section('title', 'Tenant Dashboard')

@section('content')
    <div class="container-fluid">
        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif

        <p class="text-primary fw-semibold mb-1">Tenant Administration</p>
        <h1 class="page-title mb-1">Dashboard</h1>
        <p class="text-secondary mb-4">
            Welcome back, {{ trim($tenantUser->first_name . ' ' . $tenantUser->last_name) }}.
        </p>

        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-4">
                <section class="dashboard-card h-100">
                    <p class="card-label">Store domain</p>
                    <p class="fs-5 fw-semibold mb-0 text-break">{{ $tenantDomain }}</p>
                </section>
            </div>
            <div class="col-sm-6 col-xl-4">
                <section class="dashboard-card h-100">
                    <p class="card-label">Your role</p>
                    <p class="fs-5 fw-semibold mb-0">{{ $roleNames ?: 'No role assigned' }}</p>
                </section>
            </div>
            <div class="col-sm-6 col-xl-4">
                <section class="dashboard-card h-100">
                    <p class="card-label">Account status</p>
                    <p class="fs-5 fw-semibold mb-0 text-capitalize">{{ $tenantUser->status }}</p>
                </section>
            </div>
        </div>

        <section class="dashboard-card">
            <h2 class="section-title">Store administration overview</h2>
            <p class="text-secondary mb-0">
                Your tenant dashboard is ready. Store management modules will appear in the sidebar as they are added.
            </p>
        </section>
    </div>
@endsection
