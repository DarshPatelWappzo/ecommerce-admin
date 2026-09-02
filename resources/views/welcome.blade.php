@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <p class="text-primary fw-semibold mb-1">Overview</p>
                <h1 class="page-title mb-1">Welcome back</h1>
                <p class="text-secondary mb-0">Here is what is happening with your store today.</p>
            </div>
            <button class="btn btn-primary" type="button">Create report</button>
        </div>
        <div class="row g-4">
            <div class="col-sm-6 col-xl-3"><div class="dashboard-card"><p class="card-label">Total revenue</p><h2 class="card-value">$24,580</h2><span class="text-success small fw-semibold">↑ 12.5% this month</span></div></div>
            <div class="col-sm-6 col-xl-3"><div class="dashboard-card"><p class="card-label">Orders</p><h2 class="card-value">1,248</h2><span class="text-success small fw-semibold">↑ 8.2% this month</span></div></div>
            <div class="col-sm-6 col-xl-3"><div class="dashboard-card"><p class="card-label">Customers</p><h2 class="card-value">8,540</h2><span class="text-success small fw-semibold">↑ 5.4% this month</span></div></div>
            <div class="col-sm-6 col-xl-3"><div class="dashboard-card"><p class="card-label">Conversion rate</p><h2 class="card-value">6.8%</h2><span class="text-danger small fw-semibold">↓ 1.2% this month</span></div></div>
        </div>
        <div class="dashboard-card mt-4">
            <h2 class="section-title">Getting started</h2>
            <p class="text-secondary mb-0">Your shared Bootstrap layout is ready. Add page-specific content inside each view's <code>content</code> section.</p>
        </div>
    </div>
@endsection
